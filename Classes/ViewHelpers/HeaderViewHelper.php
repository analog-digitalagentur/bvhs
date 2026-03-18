<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class HeaderViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'string', 'The input value to process', false);
    }

    public function render(): string
    {
        $value = $this->arguments['value'] ?? $this->renderChildren();
        if ($value === null) {
            return '';
        }

        return str_replace('#br#', '<br />', $value);
    }
}
