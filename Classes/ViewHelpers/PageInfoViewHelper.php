<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class PageInfoViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('uid', 'int', 'UID der Seite', true);
        $this->registerArgument('fields', 'string', 'Felder, die ausgegeben werden sollen', false, '');
        $this->registerArgument('slide', 'bool', 'Aktiviert die Slide-Funktion', false, false);
        $this->registerArgument('as', 'string', 'Name der Variable, die das Ergebnis enthält', false, 'pagedata');
    }

    public function render()
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $fields = GeneralUtility::trimExplode(',', $this->arguments['fields'], true);
        $pageData = $this->getPageData($pageRepository, $this->arguments['uid'], $fields, $this->arguments['slide']);

        $variableProvider = $this->renderingContext->getVariableProvider();
        $variableProvider->add($this->arguments['as'], $pageData);

        return $this->renderChildren();
    }

    protected function getPageData(PageRepository $pageRepository, int $uid, array $fields, bool $slide): array
    {
        $pageData = $pageRepository->getPage($uid);
        if ($fields !== []) {
            $pageData = array_intersect_key($pageData, array_flip($fields));
        }

        if ($slide && $fields !== []) {
            foreach ($fields as $field) {
                if (empty($pageData[$field])) {
                    $slidePageData = $this->slide($pageRepository, $uid, $field);
                    if (!empty($slidePageData[$field])) {
                        $pageData[$field] = $slidePageData[$field];
                    }
                }
            }
        }

        return $pageData;
    }

    protected function slide(PageRepository $pageRepository, int $uid, string $field): array
    {
        $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $uid);
        $rootLine = $rootLineUtility->get();

        foreach ($rootLine as $level) {
            $pageData = $pageRepository->getPage($level['uid']);
            if (!empty($pageData[$field])) {
                return $pageData;
            }
        }
        return [];
    }
}
