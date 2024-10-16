<?php
namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class VimeoDownloaderViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    public function initializeArguments()
    {
        $this->registerArgument('videoID', 'string', 'The ID of the Vimeo video', true);
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $videoID = $arguments['videoID'];

        if (empty($videoID)) {
            return self::generateEmptyVideoTag();
        }

        try {
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $vimeoApiToken = $extensionConfiguration->get('bvhs', 'vimeoApiToken');
            $vimeoFolder = $extensionConfiguration->get('bvhs', 'vimeoFolder');
        } catch (\Exception $e) {
            return 'Error: Unable to retrieve extension configuration';
        }

        if (empty($vimeoApiToken) || empty($vimeoFolder)) {
            return 'Error: Vimeo API token or folder not set in extension configuration';
        }

        $videoData = self::fetchVideoData($videoID, $vimeoApiToken);

        if (isset($videoData['error'])) {
            return self::generateEmptyVideoTag();
        }

        $downloadInfos = $videoData['download'];

        $existingFiles = self::getExistingFiles($vimeoFolder);
        $result = self::processVideos($downloadInfos, $vimeoFolder, $existingFiles);

        return self::generateVideoTag($result, $vimeoFolder, $downloadInfos);
    }

    private static function fetchVideoData($videoID, $apiToken)
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

    private static function getExistingFiles($vimeoFolder)
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();
        $folder = $storage->getFolder($vimeoFolder);

        $existingFiles = [];
        $files = $storage->getFilesInFolder($folder);
        foreach ($files as $file) {
            $existingFiles[$file->getName()] = $file->getName();
        }

        return $existingFiles;
    }

    private static function processVideos($downloadInfos, $vimeoFolder, $existingFiles)
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();

        if (!$storage->hasFolder($vimeoFolder)) {
            $storage->createFolder($vimeoFolder);
        }

        $folder = $storage->getFolder($vimeoFolder);
        $processedFiles = [];

        foreach ($downloadInfos as $downloadInfo) {
            $hash = md5($downloadInfo['created_time'] . $downloadInfo['size']);
            $filename = pathinfo(parse_url($downloadInfo['link'], PHP_URL_PATH), PATHINFO_BASENAME);
            $filename = urldecode($filename);
            $filenameWithHash = sprintf('%s_%s.mp4', pathinfo($filename, PATHINFO_FILENAME), $hash);

            // Extract hash from existing filename for comparison
            foreach ($existingFiles as $existingFile) {
                if (preg_match('/_(?P<hash>[a-f0-9]{32})\.mp4$/', $existingFile, $matches)) {
                    if ($matches['hash'] === $hash) {
                        $processedFiles[$downloadInfo['rendition']] = $existingFile;
                        continue 2;
                    }
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
                // Use RENAME behavior to avoid creating duplicates
                $file = $folder->addFile($tempFile, $filenameWithHash, DuplicationBehavior::REPLACE);
                $processedFiles[$downloadInfo['rendition']] = $file->getName();
                @unlink($tempFile);
            } catch (\Exception $e) {
                @unlink($tempFile);
            }
        }

        return $processedFiles;
    }

    private static function generateVideoTag($processedFiles, $vimeoFolder, $downloadInfos)
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

        if (!empty($sources)) {
            $lastSource = array_pop($sources);
            $lastSource = preg_replace('/media="[^"]+"/', 'media=""', $lastSource);
            $sources[] = $lastSource;
        }

        $videoTag = sprintf(
            '<video class="video-js" muted preload="none">%s</video>',
            implode("\n    ", $sources)
        );

        return $videoTag;
    }

    private static function generateEmptyVideoTag()
    {
        return '<video class="video-js" muted preload="none"></video>';
    }
}
