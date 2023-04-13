<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * LineToListViewHelper takes a given text string, splits it into separate lines, and converts it into an HTML list.
 *
 * Example usage:
 * <b:lineToList text="Apple{enter}Banana{enter}Cherry" class="fruit-list" />
 *
 * This will output:
 * <ul class="fruit-list">
 *   <li>Apple</li>
 *   <li>Banana</li>
 *   <li>Cherry</li>
 * </ul>
 *
 * Arguments:
 * - text (string, required): The text to convert to a list. Separate list items with line breaks.
 * - class (string, optional): The class to add to the <ul> element.
 */


class LineToListViewHelper extends AbstractViewHelper
{
    public function initializeArguments()
    {
        $this->registerArgument('text', 'string', 'The text to convert to a list', true);
        $this->registerArgument('class', 'string', 'The class to add to the <ul> element', false, '');
    }

    public function render()
    {
        $text = $this->arguments['text'];
        $class = $this->arguments['class'];

        if (!empty($text)) {
            $lines = preg_split("/\r\n|\n|\r/", $text);
            $html = "<ul class=\"" . htmlspecialchars($class) . "\">";
            foreach ($lines as $line) {
                $html .= "<li>" . htmlspecialchars(trim($line)) . "</li>";
            }
            $html .= "</ul>";
            return $html;
        }
    }
}
