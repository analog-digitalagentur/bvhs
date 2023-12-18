<?php
namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * HeadlineViewHelper formats a given text string, wraps specified parts with custom HTML tags and classes,
 * and optionally wraps the entire string with a header tag.
 *
 * Example usage:
 * <b:headline text="Welcome to the 'TYPO3' World" wrap="span" wrapclass="highlight" headertype="h1" headerclass="main-heading" />
 *
 * This will output:
 * <h1 class="main-heading">Welcome to the <span class="highlight">TYPO3</span> World</h1>
 *
 * Arguments:
 * - text (string, required): The text to format. Use single quotes around the parts that should be wrapped.
 *   Replace pipe characters '|' with '<br>' tags.
 * - wrap (string, required): The HTML tag to wrap the specified parts of the text with (e.g. "span").
 * - wrapclass (string, optional): The class to add to the wrapped HTML element.
 * - headertype (string, optional): The HTML tag to wrap the entire string with (e.g. "h1").
 * - headerclass (string, optional): The class to add to the header element.
 */

class HeadlineViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    public function initializeArguments()
    {
        $this->registerArgument("text", "string", "The text to format", true);
        $this->registerArgument("wrap", "string", "The HTML tag to wrap the first part of the text with", true);
        $this->registerArgument("wrapclass", "string", "The class to add to the wrapped HTML element", false, "");
        $this->registerArgument(
            "headertype",
            "string",
            'The HTML tag to wrap the entire string with (e.g. "h1")',
            false,
            ""
        );
        $this->registerArgument("headerclass", "string", "The class to add to the header element", false, "");
        $this->registerArgument("splitwrap", "string", "The text to format", false);
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $text = $arguments["text"];
        $wrap = $arguments["wrap"];
        $wrapclass = $arguments["wrapclass"];
        $headertype = $arguments["headertype"];
        $headerclass = $arguments["headerclass"];
        $splitwrap = $arguments["splitwrap"];

        if (empty($text)) {
            return "";
        }

        // extract the word in single quotes and replace with wrapped version
        preg_match_all("/'([^']+)'/", $text, $matches);
        foreach ($matches[1] as $word) {
            $classAttr = !empty($wrapclass) ? "class=\"$wrapclass\"" : "";
            $wrappedWord = "<{$wrap} {$classAttr}>{$word}</{$wrap}>";
            $text = str_replace("'$word'", $wrappedWord, $text);
        }

        // if splitwrap is set, split the text at the pipe character and wrap each part
        if (!empty($splitwrap)) {
            $parts = explode("|", $text);
            foreach ($parts as $index => $part) {
                $part = trim($part); // trim spaces at the start and end of the part
                $parts[$index] = "<{$splitwrap} class=\"prt--" . ($index + 1) . "\">{$part}</{$splitwrap}>";
            }
            $text = implode("", $parts);
        } else {
            // replace all occurrences of the pipe character with a <br> tag
            $text = str_replace("|", "<br>", $text);
        }

        // wrap the entire string with the specified header tag, if provided
        if (!empty($headertype)) {
            $headerclassAttr = !empty($headerclass) ? "class=\"$headerclass\"" : "";
            $html = "<{$headertype} {$headerclassAttr}>{$text}</{$headertype}>";
        } else {
            $html = $text;
        }

        return $html;
    }
}
