<?php

declare(strict_types=1);

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * PageTitleViewHelper retrieves the page title (nav_title or title) of a TYPO3 page.
 *
 * Example usage:
 * <b:pageTitle linkfield="{l2.data.tx_mask_meganavitemlink}" forcetitle="true" customtitle="{data.customtitle}" />
 *
 * Arguments:
 * - linkfield (string, required): The TYPO3 link field from the backend. Supports formats "t3://page?uid=31" and "31".
 * - forcetitle (bool, optional, default=false): Force to use the 'title' field instead of 'nav_title'.
 * - customtitle (string, optional): If set and not empty, the custom title will be used instead of fetching the page title.
 */
class PageTitleViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('linkfield', 'string', 'The TYPO3 link field from the backend', true);
        $this->registerArgument('forcetitle', 'bool', 'Force to use title field', false, false);
        $this->registerArgument('customtitle', 'string', 'Custom title to be used', false, '');
    }

    public function render(): string
    {
        $linkField = $this->arguments['linkfield'];
        $forceTitle = $this->arguments['forcetitle'];
        $customTitle = $this->arguments['customtitle'];

        if (!empty($customTitle)) {
            return $customTitle;
        }

        $pageId = $this->extractInternalPageId($linkField);

        if ($pageId === null) {
            return '';
        }

        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $page = $pageRepository->getPage($pageId);

        if ($forceTitle) {
            return $page['title'] ?? '';
        }

        return $page['nav_title'] ?: $page['title'] ?? '';
    }

    protected function extractInternalPageId(string $linkField): ?int
    {
        if (preg_match('/^t3:\/\/page\?uid=(\d+)/', $linkField, $matches)) {
            return (int)$matches[1];
        } elseif (preg_match('/^(\d+)$/', $linkField, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}
