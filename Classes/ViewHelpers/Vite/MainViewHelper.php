<?php

declare(strict_types=1);

namespace Bo\Bvhs\ViewHelpers\Vite;

use TYPO3\CMS\Core\Page\PageRenderer;
use B13\Assetcollector\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class MainViewHelper extends AbstractViewHelper
{
    public function __construct(protected AssetCollector $assetCollector, private readonly PageRenderer $pageRenderer)
    {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('outdir', 'string', 'Vite output directory', true);
        $this->registerArgument('input', 'string', 'Vite main entry file', true);
        $this->registerArgument('port', 'int', 'Vite dev server port', false, 5111);
        $this->registerArgument('additionalAttributes', 'array', 'Additional attributes for script/link tags', false, []);
        $this->registerArgument('devMode', 'bool', 'Force development mode', false, null);
    }

    public function render(): void
    {
        $absoluteOutdir = GeneralUtility::getFileAbsFileName($this->arguments['outdir']);
        $relativeOutdir = PathUtility::getAbsoluteWebPath($absoluteOutdir);

        $vueDevelopment = getenv('VUE_DEVELOPMENT');
        $viteDevelopment = getenv('VITE_DEVELOPMENT');

        $devModeOverride = $this->arguments['devMode'];
        if ($devModeOverride !== null) {
            $viteDevelopment = $devModeOverride;
            $vueDevelopment = false;
        }

        $additionalAttributes = $this->arguments['additionalAttributes'];
        $additionalAttributesString = '';
        foreach ($additionalAttributes as $key => $value) {
            $additionalAttributesString .= ' ' . htmlspecialchars((string) $key) . '="' . htmlspecialchars((string) $value) . '"';
        }

        $port = getenv('VITE_PORT') ?: $this->arguments['port'];

        if ($viteDevelopment == true || $vueDevelopment == true) {
            $pageRenderer = $this->pageRenderer;

            $domain = ($_SERVER['DDEV_PROJECT_TYPE'] ?? '') === 'typo3' ? $_SERVER['SERVER_NAME'] : 'localhost';
            $protocol = ($_SERVER['DDEV_PROJECT_TYPE'] ?? '') === 'typo3' ? 'https' : 'http';

            $pageRenderer->addHeaderData(
                '<script type="module" src="' . $protocol . '://' . $domain . ':' . $port . '/@vite/client"' . $additionalAttributesString . '></script>'
            );
            $pageRenderer->addHeaderData(
                '<script type="module" src="' . $protocol . '://' . $domain . ':' . $port . '/' . $this->arguments['input'] . '"' . $additionalAttributesString . '></script>'
            );
        } else {
            $file = null;
            if (file_exists($absoluteOutdir . '/manifest.json')) {
                $file = file_get_contents($absoluteOutdir . '/manifest.json');
            } elseif (file_exists($absoluteOutdir . '/.vite/manifest.json')) {
                $file = file_get_contents($absoluteOutdir . '/.vite/manifest.json');
            }

            if ($file !== null) {
                $manifest = json_decode($file, true);

                if (!str_ends_with($relativeOutdir, '/')) {
                    $relativeOutdir .= '/';
                }

                if (!isset($manifest[$this->arguments['input']])) {
                    return;
                }

                $mainFile = $relativeOutdir . $manifest[$this->arguments['input']]['file'];

                if ($mainFile !== '' && $mainFile !== '0') {
                    $fileExtension = pathinfo($mainFile, PATHINFO_EXTENSION);

                    switch ($fileExtension) {
                        case 'js':
                            $this->assetCollector->addJavaScriptFile($mainFile, array_merge(['type' => 'module', 'async' => 'true'], $additionalAttributes));
                            break;
                        case 'css':
                            if (str_starts_with($mainFile, '/')) {
                                $mainFile = substr($mainFile, 1);
                            }
                            $this->assetCollector->addCssFile($mainFile);
                            break;
                    }
                }

                if (isset($manifest[$this->arguments['input']]['css'])) {
                    foreach ($manifest[$this->arguments['input']]['css'] as $maincssfile) {
                        if (str_starts_with($relativeOutdir, '/')) {
                            $relativeOutdir = substr($relativeOutdir, 1);
                        }
                        $this->assetCollector->addCssFile($relativeOutdir . $maincssfile);
                    }
                }
            }
        }
    }
}
