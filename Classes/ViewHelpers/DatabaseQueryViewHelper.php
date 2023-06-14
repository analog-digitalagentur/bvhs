<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * DatabaseQueryViewHelper führt eine Datenbankabfrage auf einer bestimmten Tabelle durch und gibt die Ergebnisse zurück.
 *
 * Beispiel:
 * <b:databaseQuery table="tx_myextension_domain_model_record" pidInList="123" where="title = 'Test'" orderBy="uid DESC" as="records">
 *      <f:debug>{records}</f:debug>
 * </b:databaseQuery>
 *
 * Dieser ViewHelper führt eine Datenbankabfrage auf der Tabelle 'tx_myextension_domain_model_record' durch, wählt Datensätze
 * von der Seite mit der ID 123 aus, bei denen der Titel 'Test' ist und sortiert sie in absteigender Reihenfolge nach der 'uid'.
 * Die Ergebnisse der Abfrage werden in der Variable 'records' gespeichert und können anschließend z.B. in einer f:debug Anweisung angezeigt werden.
 *
 * Argumente:
 * - table (string, erforderlich): Der Name der Tabelle, aus der Daten ausgewählt werden sollen.
 * - pidInList (int, optional): Die Seiten-ID-Liste, aus der Daten abgerufen werden sollen. Der Standardwert ist 0, was alle Seiten betrifft.
 * - where (string, optional): Die WHERE-Klausel für die Abfrage. Der Standardwert ist '', was bedeutet, dass keine zusätzlichen Filter angewendet werden.
 * - orderBy (string, optional): Die ORDER BY-Klausel für die Abfrage. Der Standardwert ist '', was bedeutet, dass keine bestimmte Sortierreihenfolge angewendet wird.
 * - as (string, optional): Der Name der Variable, in der die Ergebnisse der Abfrage gespeichert werden. Der Standardwert ist 'records'.
 *
 * Hinweis: Dieser ViewHelper führt nur SELECT-Abfragen aus und gibt die Ergebnisse zurück. Er kann nicht zum Aktualisieren oder Löschen von Daten verwendet werden.
 */

class DatabaseQueryViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;
    
    protected $escapeOutput = false;

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('table', 'string', 'The table to select data from', true);
        $this->registerArgument('pidInList', 'int', 'Page id list to fetch data from', false, 0);
        $this->registerArgument('where', 'string', 'Where clause for the query', false, '');
        $this->registerArgument('orderBy', 'string', 'Order by clause for the query', false, '');
        $this->registerArgument('as', 'string', 'Variable name to assign the data to', false, 'records');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return mixed
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($arguments['table']);

        $records = $queryBuilder
            ->select('*')
            ->from($arguments['table'])
            ->where($arguments['where'])
            ->orderBy($arguments['orderBy'])
            ->execute()
            ->fetchAll();

        $templateVariableContainer = $renderingContext->getVariableProvider();

        $previousData = null;

        if ($templateVariableContainer->exists($arguments['as'])) {
            $previousData = $templateVariableContainer->get($arguments['as']);
            $templateVariableContainer->remove($arguments['as']);
        }

        $templateVariableContainer->add($arguments['as'], $records);

        $output = $renderChildrenClosure();

        $templateVariableContainer->remove($arguments['as']);

        if ($previousData) {
            $templateVariableContainer->add($arguments['as'], $previousData);
        }

        return $output;
    }
}
