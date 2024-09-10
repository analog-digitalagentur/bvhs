<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class CookieVideoViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'iframe';

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerArgument('file', 'mixed', 'File', true);
        $this->registerArgument('width', 'string', 'Width of the iframe', false, '560');
        $this->registerArgument('height', 'string', 'Height of the iframe', false, '315');
        $this->registerArgument('additionalConfig', 'array', 'Additional configuration for the iframe', false, []);
    }

    public function render()
    {
        $file = $this->arguments['file'];
        $width = $this->arguments['width'];
        $height = $this->arguments['height'];
        $additionalConfig = $this->arguments['additionalConfig'];

        if ($file instanceof ExtbaseFileReference) {
            $file = $file->getOriginalResource();
        }

        if (!$file instanceof FileInterface) {
            throw new \UnexpectedValueException('Supplied file object type ' . get_class($file) . ' must be FileInterface.', 1454252193);
        }

        return $this->renderIframe($file, $width, $height, $additionalConfig);
    }

    protected function renderIframe(FileInterface $file, $width, $height, array $additionalConfig)
    {
        $url = $file->getPublicUrl();
        $src = $this->getEmbedUrl($url);

        // Use data-cookieblock-src instead of src
        $this->tag->addAttribute('data-cookieblock-src', $src);
        $this->tag->addAttribute('data-cookieconsent', 'marketing');
        $this->tag->addAttribute('frameborder', '0');
        $this->tag->addAttribute('allowfullscreen', '');

        // Add width and height as inline styles
        $style = sprintf('width: %s; height: %s;', $width, $height);
        $this->tag->addAttribute('style', $style);

        // Add any additional config as attributes
        foreach ($additionalConfig as $key => $value) {
            $this->tag->addAttribute($key, $value);
        }

        // Ensure the tag is not self-closing
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }

    protected function getEmbedUrl($url)
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return $this->getYouTubeEmbedUrl($url);
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return $this->getVimeoEmbedUrl($url);
        } else {
            throw new \InvalidArgumentException('Unsupported video URL: ' . $url);
        }
    }

    protected function getYouTubeEmbedUrl($url)
    {
        $videoId = $this->extractYouTubeId($url);
        return 'https://www.youtube.com/embed/' . $videoId;
    }

    protected function getVimeoEmbedUrl($url)
    {
        $videoId = $this->extractVimeoId($url);
        return 'https://player.vimeo.com/video/' . $videoId;
    }

    protected function extractYouTubeId($url)
    {
        $videoId = '';
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
        if (preg_match($pattern, $url, $match)) {
            $videoId = $match[1];
        }
        return $videoId;
    }

    protected function extractVimeoId($url)
    {
        $videoId = '';
        $pattern = '/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/i';
        if (preg_match($pattern, $url, $match)) {
            $videoId = $match[1];
        }
        return $videoId;
    }
}