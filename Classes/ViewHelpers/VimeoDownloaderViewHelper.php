<?php
namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class VimeoDownloaderViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;
    private static $cacheFile = 'typo3temp/var/cache/bvhs/vimeo_cache.json';
    private static $cacheTimeout = 86400; // 24 Stunden in Sekunden
    private static $maxAge = 2592000;
    public function __construct(private readonly ResourceFactory $resourceFactory)
    {
    } // 30 Tage in Sekunden

    public function initializeArguments(): void
    {
        $this->registerArgument('videoID', 'string', 'The ID of the Vimeo video', true);
        $this->registerArgument('id', 'string', 'The ID attribute for the video tag', false);
        $this->registerArgument('class', 'string', 'The class attribute for the video tag', false);
        $this->registerArgument('preload', 'string', 'The preload attribute for the video tag', false);
        $this->registerArgument('muted', 'boolean', 'Whether the video should be muted', false, false);
        $this->registerArgument('loop', 'boolean', 'Whether the video should loop', false, false);
        $this->registerArgument('controls', 'boolean', 'Whether to show video controls', false, false);
        $this->registerArgument('autoplay', 'boolean', 'Whether the video should autoplay', false, false);
        $this->registerArgument('poster', 'string', 'URL for the video poster image', false);
        $this->registerArgument('playsinline', 'boolean', 'Whether the video should play inline on mobile devices', false, false);
        $this->registerArgument('useCache', 'string', 'Whether to use 24h cache for video checks (1/0)', false, '1');
    }

    public function render(): string
    {
        $videoID = $this->arguments['videoID'];
        $useCache = filter_var($this->arguments['useCache'], FILTER_VALIDATE_BOOLEAN);

        if (empty($videoID)) {
            return $this->generateEmptyVideoTag($this->arguments);
        }

        try {
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $vimeoApiToken = $extensionConfiguration->get('bvhs', 'vimeoApiToken');
            $vimeoFolder = $extensionConfiguration->get('bvhs', 'vimeoFolder');
        } catch (\Exception) {
            return 'Error: Unable to retrieve extension configuration';
        }

        if (empty($vimeoApiToken) || empty($vimeoFolder)) {
            return 'Error: Vimeo API token or folder not set in extension configuration';
        }

        if ($useCache) {
            $cacheData = $this->loadCache();
            if (!$this->needsUpdate($videoID, $cacheData)) {
                return $this->generateVideoTag($cacheData[$videoID]['files'], $vimeoFolder, $cacheData[$videoID]['download_infos'], $this->arguments);
            }
        }

        $videoData = $this->fetchVideoData($videoID, $vimeoApiToken);

        if (isset($videoData['error'])) {
            return $this->generateEmptyVideoTag($this->arguments);
        }

        $downloadInfos = $videoData['download'];
        $existingFiles = $this->getExistingFiles($vimeoFolder);
        $processedFiles = $this->processVideos($downloadInfos, $vimeoFolder, $existingFiles, $vimeoApiToken);

        if ($useCache) {
            $this->updateCache($videoID, $processedFiles, $downloadInfos);
        }

        return $this->generateVideoTag($processedFiles, $vimeoFolder, $downloadInfos, $this->arguments);
    }

    private function loadCache()
    {
        $cacheFilePath = GeneralUtility::getFileAbsFileName(self::$cacheFile);
        if (file_exists($cacheFilePath)) {
            $cacheContent = file_get_contents($cacheFilePath);
            $cacheData = json_decode($cacheContent, true) ?: [];

            // Lösche alte Einträge
            $this->cleanOldEntries($cacheData);

            return $cacheData;
        }
        return [];
    }

    private function cleanOldEntries(&$cacheData): void
    {
        $now = time();
        $modified = false;

        foreach ($cacheData as $videoID => $data) {
            if (($now - $data['last_check']) > self::$maxAge) {
                unset($cacheData[$videoID]);
                $modified = true;
            }
        }

        // Wenn Einträge gelöscht wurden, speichere die aktualisierte Datei
        if ($modified) {
            $cacheFilePath = GeneralUtility::getFileAbsFileName(self::$cacheFile);
            file_put_contents($cacheFilePath, json_encode($cacheData, JSON_PRETTY_PRINT));
        }
    }

    private function needsUpdate($videoID, $cacheData)
    {
        if (!isset($cacheData[$videoID])) {
            return true;
        }

        $lastUpdate = $cacheData[$videoID]['last_check'] ?? 0;
        return (time() - $lastUpdate) >= self::$cacheTimeout;
    }

    private function updateCache($videoID, $files, $downloadInfos): void
    {
        $cacheFilePath = GeneralUtility::getFileAbsFileName(self::$cacheFile);
        $cacheDir = dirname($cacheFilePath);

        // Stelle sicher, dass das Verzeichnis existiert
        if (!is_dir($cacheDir)) {
            GeneralUtility::mkdir_deep($cacheDir);
        }

        $cacheData = $this->loadCache();
        $cacheData[$videoID] = [
            'last_check' => time(),
            'files' => $files,
            'download_infos' => $downloadInfos
        ];

        file_put_contents($cacheFilePath, json_encode($cacheData, JSON_PRETTY_PRINT));
    }

    private function fetchVideoData($videoID, $apiToken)
    {
        $url = "https://api.vimeo.com/videos/{$videoID}?fields=download";
        $options = [
            'http' => [
                'header' => "Authorization: bearer {$apiToken}\r\n",
                'method' => 'GET'
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['error' => 'Failed to fetch video data from Vimeo API'];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse API response'];
        }

        return $data;
    }

    private function getExistingFiles($vimeoFolder)
    {
        $resourceFactory = $this->resourceFactory;
        $storage = $resourceFactory->getDefaultStorage();
        $folder = $storage->getFolder($vimeoFolder);

        $existingFiles = [];
        $files = $storage->getFilesInFolder($folder);
        foreach ($files as $file) {
            $existingFiles[$file->getName()] = $file->getName();
        }

        return $existingFiles;
    }

    private function processVideos($downloadInfos, $vimeoFolder, $existingFiles, $vimeoApiToken)
    {
        $resourceFactory = $this->resourceFactory;
        $storage = $resourceFactory->getDefaultStorage();

        if (!$storage->hasFolder($vimeoFolder)) {
            $storage->createFolder($vimeoFolder);
        }

        $folder = $storage->getFolder($vimeoFolder);
        $processedFiles = [];

        foreach ($downloadInfos as $downloadInfo) {
            $hash = md5($downloadInfo['created_time'] . $downloadInfo['size']);
            $filename = pathinfo(parse_url((string) $downloadInfo['link'], PHP_URL_PATH), PATHINFO_BASENAME);
            $filename = urldecode($filename);
            $filenameWithHash = sprintf('%s_%s.mp4', pathinfo($filename, PATHINFO_FILENAME), $hash);

            // Extract hash from existing filename for comparison
            foreach ($existingFiles as $existingFile) {
                if (preg_match('/_(?P<hash>[a-f0-9]{32})\.mp4$/', $existingFile, $matches) && $matches['hash'] === $hash) {
                    $processedFiles[$downloadInfo['rendition']] = $existingFile;
                    continue 2;
                }
            }

            // Download only if the file doesn't exist
            $tempFile = GeneralUtility::tempnam('vimeo_download_');
            $downloadLink = $downloadInfo['link'];
            $contextOptions = [
                'http' => [
                    'header' => "Authorization: bearer {$vimeoApiToken}\r\n",
                    'method' => 'GET'
                ]
            ];
            $context = stream_context_create($contextOptions);
            $success = @file_get_contents($downloadLink, false, $context);

            if ($success === false) {
                @unlink($tempFile);
                continue;
            }

            file_put_contents($tempFile, $success);

            try {
                $file = $folder->addFile($tempFile, $filenameWithHash, DuplicationBehavior::REPLACE);
                $processedFiles[$downloadInfo['rendition']] = $file->getName();
                @unlink($tempFile);
            } catch (\Exception) {
                @unlink($tempFile);
            }
        }

        return $processedFiles;
    }

    private function generateVideoTag($processedFiles, $vimeoFolder, $downloadInfos, $arguments)
    {
        $sources = [];
        $widths = [];

        foreach ($downloadInfos as $info) {
            $widths[$info['rendition']] = $info['width'];
        }
        arsort($widths);

        $prevWidth = PHP_INT_MAX;
        foreach ($widths as $rendition => $width) {
            if (isset($processedFiles[$rendition])) {
                $mediaQuery = $prevWidth === PHP_INT_MAX
                    ? "(min-width: {$width}px)"
                    : "(min-width: {$width}px) and (max-width: {$prevWidth}px)";

                $sources[] = sprintf(
                    '<source src="/fileadmin/%s/%s" type="video/mp4" media="%s">',
                    $vimeoFolder,
                    $processedFiles[$rendition],
                    $mediaQuery
                );

                $prevWidth = $width - 1;
            }
        }

        if ($sources !== []) {
            $lastSource = array_pop($sources);
            $lastSource = preg_replace('/media="[^"]+"/', 'media=""', $lastSource);
            $sources[] = $lastSource;
        }

        $attributes = [];

        if (!empty($arguments['id'])) {
            $attributes['id'] = $arguments['id'];
        }

        if (!empty($arguments['class'])) {
            $attributes['class'] = $arguments['class'];
        }

        if (!empty($arguments['preload'])) {
            $attributes['preload'] = $arguments['preload'];
        }

        if ($arguments['muted']) {
            $attributes['muted'] = '';
        }

        if ($arguments['loop']) {
            $attributes['loop'] = '';
        }

        if ($arguments['controls']) {
            $attributes['controls'] = '';
        }

        if ($arguments['autoplay']) {
            $attributes['autoplay'] = '';
        }

        if (!empty($arguments['poster'])) {
            $attributes['poster'] = $arguments['poster'];
        }

        if ($arguments['playsinline']) {
            $attributes['playsinline'] = '';
        }

        $attributeString = '';
        foreach ($attributes as $key => $value) {
            if ($value === '') {
                $attributeString .= ' ' . $key;
            } else {
                $attributeString .= sprintf(' %s="%s"', $key, htmlspecialchars((string) $value));
            }
        }

        $videoTag = sprintf(
            '<video%s>%s</video>',
            $attributeString,
            implode("\n    ", $sources)
        );

        return $videoTag;
    }

    private function generateEmptyVideoTag($arguments)
    {
        return '<video></video>';
    }
}
