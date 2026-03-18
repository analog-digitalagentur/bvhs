<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class CookieVideoViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'iframe';

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('file', 'mixed', 'File', true);
        $this->registerArgument('width', 'string', 'Width of the iframe', false, '560');
        $this->registerArgument('height', 'string', 'Height of the iframe', false, '315');
        $this->registerArgument('additionalConfig', 'array', 'Additional configuration for the iframe', false, []);
        $this->registerArgument('additionalAttributes', 'array', 'Additional attributes for the iframe', false, []);
    }

    public function render(): string
    {
        $file = $this->arguments['file'];
        $width = $this->arguments['width'];
        $height = $this->arguments['height'];
        $additionalConfig = $this->arguments['additionalConfig'];

        if ($file instanceof ExtbaseFileReference) {
            $file = $file->getOriginalResource();
        }

        if (!$file instanceof FileInterface) {
            throw new \UnexpectedValueException('Supplied file object type ' . $file::class . ' must be FileInterface.', 1454252193);
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

        // Add width and height as inline styles if not overridden by additionalAttributes
        if (!isset($this->arguments['additionalAttributes']['style'])) {
            $style = sprintf('width: %s; height: %s;', $width, $height);
            $this->tag->addAttribute('style', $style);
        }

        // Add any additional config as attributes
        foreach ($additionalConfig as $key => $value) {
            $this->tag->addAttribute($key, $value);
        }

        // Add any additional attributes
        if (isset($this->arguments['additionalAttributes']) && is_array($this->arguments['additionalAttributes'])) {
            foreach ($this->arguments['additionalAttributes'] as $key => $value) {
                $this->tag->addAttribute($key, $value);
            }
        }

        // Ensure the tag is not self-closing
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }

    protected function getEmbedUrl($url)
    {
        if (str_contains((string) $url, 'youtube.com') || str_contains((string) $url, 'youtu.be')) {
            return $this->getYouTubeEmbedUrl($url);
        } elseif (str_contains((string) $url, 'vimeo.com')) {
            return $this->getVimeoEmbedUrl($url);
        } else {
            throw new \InvalidArgumentException('Unsupported video URL: ' . $url, 2010610079);
        }
    }

    protected function getYouTubeEmbedUrl($url)
    {
        $videoId = $this->extractYouTubeId($url);
        return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?rel=0&modestbranding=1';
    }

    protected function getVimeoEmbedUrl($url)
    {
        $videoId = $this->extractVimeoId($url);
        return 'https://player.vimeo.com/video/' . $videoId . '?dnt=1&app_id=122963';
    }

    protected function extractYouTubeId($url)
    {
        $videoId = '';
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
        if (preg_match($pattern, (string) $url, $match)) {
            $videoId = $match[1];
        }
        return $videoId;
    }

    protected function extractVimeoId($url)
    {
        $videoId = '';
        $pattern = '/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/i';
        if (preg_match($pattern, (string) $url, $match)) {
            $videoId = $match[1];
        }
        return $videoId;
    }
}
