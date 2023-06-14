<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * PageFieldSlideViewHelper gibt Informationen über ein bestimmtes Feld einer TYPO3-Seite zurück und "slided" in der Seitenhierarchie.
 *
 * Beispiel:
 * <b:pageFieldSlide uid="{data.uid}" field="tx_site_package_navigation_item_footer" as="fieldinfo">
 *      <f:debug>{fieldinfo}</f:debug>
 * </b:pageFieldSlide>
 *
 * Dieser ViewHelper gibt den Wert des Feldes 'tx_site_package_navigation_item_footer' der Seite mit der ID {data.uid} zurück.
 * Wenn das Feld leer ist, klettert der ViewHelper die Seitenhierarchie hinauf (slide), bis er einen Wert für das leere Feld findet.
 * Das Ergebnis wird in der Variablen 'fieldinfo' gespeichert.
 *
 * Der ausgegebene Wert sieht folgendermaßen aus:
 * {
 *      tx_site_package_navigation_item_footer: value
 *      valuePID = 55 #current pid
 * }
 * Oder wenn das Feld auf der aktuellen Seite nicht befüllt ist und es einen Wert in der Hierarchie findet:
 * {
 *      tx_site_package_navigation_item_footer: value
 *      valuePID = 2 #pid of page with next entry
 * }
 *
 * Argumente:
 * - uid (int, erforderlich): Die ID der Seite, von der die Informationen abgerufen werden sollen.
 * - field (string, erforderlich): Der Name des Feldes, das zurückgegeben werden soll.
 * - as (string, optional): Der Name der Variable, in der das Ergebnis gespeichert wird. Standardwert ist 'fieldinfo'.
 *
 * Inline-Verwendung:
 * {b:pageFieldSlide(uid: data.uid, field: 'tx_site_package_navigation_item_footer')}
 */

class PageFieldSlideViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    public function initializeArguments()
    {
        $this->registerArgument('uid', 'int', 'UID der Seite', true);
        $this->registerArgument('field', 'string', 'Das Feld, das ausgegeben werden soll', true);
        $this->registerArgument('as', 'string', 'Name der Variable, die das Ergebnis enthält', false, 'fieldinfo');
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $tsfe = static::getTypoScriptFrontendController();
        $fieldData = static::getFieldData($tsfe, $arguments['uid'], $arguments['field']);

        $variableProvider = $renderingContext->getVariableProvider();
        $variableProvider->add($arguments['as'], $fieldData);

        return $renderChildrenClosure();
    }

    protected static function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    protected static function getFieldData(TypoScriptFrontendController $tsfe, int $uid, string $field): array
    {
        $pageData = $tsfe->sys_page->getPage($uid);
        if (empty($pageData[$field])) {
            $slidePageData = static::slide($tsfe, $uid, $field);
            if (!empty($slidePageData[$field])) {
                return [
                    $field => $slidePageData[$field],
                    'valuePID' => $slidePageData['uid']
                ];
            }
        } else {
            return [
                $field => $pageData[$field],
                'valuePID' => $uid
            ];
        }
        return [];
    }

    protected static function slide(TypoScriptFrontendController $tsfe, int $uid, string $field): array
    {
        $rootLineUtility = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Utility\RootlineUtility::class, $uid);
        $rootLine = $rootLineUtility->get();

        foreach ($rootLine as $level) {
            $pageData = $tsfe->sys_page->getPage($level['uid']);
            if (!empty($pageData[$field])) {
                return $pageData;
            }
        }
        return [];
    }
}
