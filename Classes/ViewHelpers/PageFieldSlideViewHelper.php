<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class PageFieldSlideViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('uid', 'int', 'UID der Seite', true);
        $this->registerArgument('field', 'string', 'Das Feld, das ausgegeben werden soll', true);
        $this->registerArgument('as', 'string', 'Name der Variable, die das Ergebnis enthält', false, 'fieldinfo');
    }

    public function render()
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $fieldData = $this->getFieldData($pageRepository, $this->arguments['uid'], $this->arguments['field']);

        $variableProvider = $this->renderingContext->getVariableProvider();
        $variableProvider->add($this->arguments['as'], $fieldData);

        return $this->renderChildren();
    }

    protected function getFieldData(PageRepository $pageRepository, int $uid, string $field): array
    {
        $pageData = $pageRepository->getPage($uid);
        if (empty($pageData[$field])) {
            $slidePageData = $this->slide($pageRepository, $uid, $field);
            if (!empty($slidePageData[$field])) {
                return [
                    $field => $slidePageData[$field],
                    'valuePID' => $slidePageData['uid'],
                ];
            }
        } else {
            return [
                $field => $pageData[$field],
                'valuePID' => $uid,
            ];
        }
        return [];
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
