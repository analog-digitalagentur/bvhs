<?php
namespace Bo\Bvhs\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * PageInfoViewHelper gibt Informationen über eine bestimmte TYPO3-Seite zurück.
 *
 * Beispiel:
 * <b:pageInfo uid="{data.pid}" fields="title, address, zip" slide="true" as="pagedata">
 *      <f:debug>{pagedata}</f:debug>
 * </b:pageInfo>
 *
 * Dieser ViewHelper gibt die Felder 'title', 'address' und 'zip' der Seite mit der ID {data.pid} zurück. Wenn eines
 * der Felder leer ist und das Attribut 'slide' auf 'true' gesetzt ist, klettert der ViewHelper die Seitenhierarchie
 * hinauf (slide), bis er einen Wert für das leere Feld findet. Das Ergebnis wird in der Variablen 'pagedata' gespeichert.
 *
 * Argumente:
 * - uid (int, erforderlich): Die ID der Seite, von der die Informationen abgerufen werden sollen.
 * - fields (string, optional): Eine kommaseparierte Liste der Felder, die zurückgegeben werden sollen.
 *   Wenn dieses Attribut nicht gesetzt ist, werden alle Felder der Seite zurückgegeben.
 * - slide (bool, optional): Wenn auf 'true' gesetzt, aktiviert die Slide-Funktion. Standardwert ist 'false'.
 * - as (string, optional): Der Name der Variable, in der das Ergebnis gespeichert wird. Standardwert ist 'pagedata'.
 */

class PageInfoViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initialisierung der Argumente
     */
    public function initializeArguments()
    {
        $this->registerArgument('uid', 'int', 'UID der Seite', true);
        $this->registerArgument('fields', 'string', 'Felder, die ausgegeben werden sollen', false, '');
        $this->registerArgument('slide', 'bool', 'Aktiviert die Slide-Funktion', false, false);
        $this->registerArgument('as', 'string', 'Name der Variable, die das Ergebnis enthält', false, 'pagedata');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $tsfe = static::getTypoScriptFrontendController();
        $fields = GeneralUtility::trimExplode(',', $arguments['fields'], true);
        $pageData = static::getPageData($tsfe, $arguments['uid'], $fields, $arguments['slide']);

        $variableProvider = $renderingContext->getVariableProvider();
        $variableProvider->add($arguments['as'], $pageData);

        return $renderChildrenClosure();
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected static function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @param TypoScriptFrontendController $tsfe
     * @param int $uid
     * @param array $fields
     * @param bool $slide
     * @return array
     */
    protected static function getPageData(TypoScriptFrontendController $tsfe, int $uid, array $fields, bool $slide): array
    {
        $pageData = $tsfe->sys_page->getPage($uid);
        if (!empty($fields)) {
            $pageData = array_intersect_key($pageData, array_flip($fields));
        }

        if ($slide === true && !empty($fields)) {
            foreach ($fields as $field) {
                if (empty($pageData[$field])) {
                    $slidePageData = static::slide($tsfe, $uid, $field);
                    if (!empty($slidePageData[$field])) {
                        $pageData[$field] = $slidePageData[$field];
                    }
                }
            }
        }

        return $pageData;
    }


    /**
     * @param TypoScriptFrontendController $tsfe
     * @param int $uid
     * @param string $field
     * @return array
     */
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
