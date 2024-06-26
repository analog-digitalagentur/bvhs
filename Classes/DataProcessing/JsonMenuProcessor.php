<?php
namespace Bo\Bvhs\DataProcessing;

use TYPO3\CMS\Frontend\DataProcessing\MenuProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * JsonMenuProcessor generiert ein Menü und gibt es als JSON-String zurück.
 *
 * Dieser Prozessor erweitert die Funktionalität des StandardMenuProcessors, indem er das generierte Menü in einen JSON-String umwandelt.
 * Zusätzlich entfernt er die 'data'-Eigenschaft aus jedem Menüelement, um die Datenmenge zu reduzieren.
 *
 * Beispiel TypoScript-Konfiguration:
 * 10 = Bo\Bvhs\DataProcessing\JsonMenuProcessor
 * 10 {
 *    special = directory
 *    special.value = 1
 *    levels = 2
 *    as = mainMenu
 * }
 *
 * Dieser Prozessor generiert ein Menü basierend auf dem Verzeichnis mit der ID 1, geht 2 Ebenen tief und speichert das Ergebnis unter dem Schlüssel 'mainMenu'.
 * Das resultierende JSON-Menü wird unter dem Schlüssel 'jsonMenu' im $processedData Array gespeichert.
 *
 * In einem Fluid-Template kann das JSON-Menü wie folgt verwendet werden:
 * <my-element data-menu="{jsonMenu -> f:format.raw()}"></my-element>
 *
 * Argumente:
 * Alle Argumente des StandardMenuProcessors werden unterstützt. Zusätzlich gibt es:
 *
 * as (string, optional): Der Name der Variable, unter der das Menü im $processedData Array gespeichert wird. Standard ist 'menu'.
 *
 * Hinweis: Dieser Prozessor entfernt die 'data'-Eigenschaft aus jedem Menüelement, um die Datenmenge zu reduzieren.
 * Wenn Sie diese Eigenschaft benötigen, müssen Sie den Prozessor entsprechend anpassen.
 */
class JsonMenuProcessor implements DataProcessorInterface
{
    protected MenuProcessor $menuProcessor;

    public function __construct()
    {
        $this->menuProcessor = GeneralUtility::makeInstance(
            MenuProcessor::class,
            GeneralUtility::makeInstance(ContentDataProcessor::class),
            GeneralUtility::makeInstance(ContentObjectFactory::class)
        );
    }

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ) {
        $processedData = $this->menuProcessor->process($cObj, $contentObjectConfiguration, $processorConfiguration, $processedData);

        $menuData = $processedData[$processorConfiguration['as'] ?? 'menu'] ?? [];

        // Entfernen der 'data'-Eigenschaft rekursiv
        $strippedMenuData = $this->stripDataProperty($menuData);

        // Konvertieren Sie das bereinigte Menü in JSON
        $jsonMenu = json_encode($strippedMenuData);

        // Fügen Sie das JSON-Menü als neuen Schlüssel zum $processedData Array hinzu
        $processedData['jsonMenu'] = $jsonMenu;

        return $processedData;
    }

    /**
     * Entfernt rekursiv die 'data'-Eigenschaft aus dem Menü-Array.
     *
     * @param array $menu Das zu bereinigende Menü-Array
     * @return array Das bereinigte Menü-Array
     */
    protected function stripDataProperty(array $menu): array
    {
        return array_map(function ($item) {
            unset($item['data']);
            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->stripDataProperty($item['children']);
            }
            return $item;
        }, $menu);
    }
}
