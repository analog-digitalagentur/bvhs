<?php

namespace Bo\Bvhs\DataProcessing;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\Resource\FileCollector;

class SingleFileProcessor implements DataProcessorInterface
{
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);

        // references / relations
        if (
            (isset($processorConfiguration['references']) && $processorConfiguration['references'])
            || (isset($processorConfiguration['references.']) && $processorConfiguration['references.'])
        ) {
            $referencesUidList = (string)$cObj->stdWrapValue('references', $processorConfiguration);
            $referencesUids = GeneralUtility::intExplode(',', $referencesUidList, true);
            $fileCollector->addFileReferences($referencesUids);

            if (!empty($processorConfiguration['references.'])) {
                $referenceConfiguration = $processorConfiguration['references.'];
                $relationField = $cObj->stdWrapValue('fieldName', $referenceConfiguration ?? []);

                if (!empty($relationField)) {
                    $relationTable = $cObj->stdWrapValue('table', $referenceConfiguration, $cObj->getCurrentTable());
                    if (!empty($relationTable)) {
                        $fileCollector->addFilesFromRelation($relationTable, $relationField, $cObj->data);
                    }
                }
            }
        }

        // files
        $files = $cObj->stdWrapValue('files', $processorConfiguration);
        if ($files) {
            $filesUids = GeneralUtility::intExplode(',', (string)$files, true);
            $fileCollector->addFiles($filesUids);
        }

        // collections
        $collections = $cObj->stdWrapValue('collections', $processorConfiguration);
        if (!empty($collections)) {
            $collectionsUids = GeneralUtility::intExplode(',', (string)$collections, true);
            $fileCollector->addFilesFromFileCollections($collectionsUids);
        }

        // folders
        $folders = $cObj->stdWrapValue('folders', $processorConfiguration);
        if (!empty($folders)) {
            $folderPaths = GeneralUtility::trimExplode(',', (string)$folders, true);
            $fileCollector->addFilesFromFolders($folderPaths, !empty($processorConfiguration['folders.']['recursive']));
        }

        // Sortieren, falls gewünscht
        $sortingProperty = $cObj->stdWrapValue('sorting', $processorConfiguration);
        if ($sortingProperty) {
            $sortingDirection = $cObj->stdWrapValue('direction', $processorConfiguration['sorting.'] ?? [], 'ascending');
            $fileCollector->sort($sortingProperty, $sortingDirection);
        }

        // Hole die Dateien und setze nur das erste File-Objekt
        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'file');
        $files = $fileCollector->getFiles();
        $processedData[$targetVariableName] = reset($files) ?: null;

        return $processedData;
    }
}
