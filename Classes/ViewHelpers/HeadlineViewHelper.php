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
 * <b:headline text="'Welcome' | to our `amazing` website" wrap="span" wrapclass="highlight" headertype="h1" headerclass="main-heading" specialWrap="span" specialWrapClass="special" />
 *
 * This will output:
 * <h1 class="main-heading"><span class="prt--1"><span class="highlight">Welcome</span></span><span class="prt--2">to our <span class="special">amazing</span> website</span></h1>
 *
 * Arguments:
 * - text (string, required): The text to format. Use single quotes around the parts that should be wrapped.
 *   Use pipe characters '|' to split the text into parts.
 * - wrap (string, required): The HTML tag to wrap the specified parts of the text with (e.g. "span").
 * - wrapclass (string, optional): The class to add to the wrapped HTML element.
 * - headertype (string, optional): The HTML tag to wrap the entire string with (e.g. "h1").
 * - headerclass (string, optional): The class to add to the header element.
 * - splitwrap (string, optional): The HTML tag to wrap each part of the split text with.
 * - specialWrap (string, optional): The HTML tag to wrap text within backticks.
 * - specialWrapClass (string, optional): The class to add to the special wrapped HTML element.
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
        $this->registerArgument("headertype", "string", 'The HTML tag to wrap the entire string with (e.g. "h1")', false, "");
        $this->registerArgument("headerclass", "string", "The class to add to the header element", false, "");
        $this->registerArgument("splitwrap", "string", "The HTML tag to wrap each part of the split text with", false, "");
        $this->registerArgument("specialWrap", "string", "The HTML tag to wrap text within backticks", false, "span");
        $this->registerArgument("specialWrapClass", "string", "The class to add to the special wrapped HTML element", false, "special");
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $text = $arguments["text"] ?? "";
        $wrap = $arguments["wrap"] ?? "";
        $wrapclass = $arguments["wrapclass"] ?? "";
        $headertype = $arguments["headertype"] ?? "";
        $headerclass = $arguments["headerclass"] ?? "";
        $splitwrap = $arguments["splitwrap"] ?? "";
        $specialWrap = $arguments["specialWrap"] ?? "span";
        $specialWrapClass = $arguments["specialWrapClass"] ?? "special";

        if (empty($text)) {
            return "";
        }

        // Get extension configuration
        $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
        )->get('bvhs');

        // Get line break placeholder from configuration or use default
        $lineBreakPlaceholder = $extensionConfiguration['lineBreakPlaceholder'] ?? '|';

        // Extract and wrap words in single quotes
        $text = preg_replace_callback("/'([^']+)'/", function ($matches) use ($wrap, $wrapclass) {
            $classAttr = !empty($wrapclass) ? " class=\"$wrapclass\"" : "";
            return "<{$wrap}{$classAttr}>{$matches[1]}</{$wrap}>";
        }, $text);

        // Wrap text in backticks with special wrap
        $text = preg_replace_callback("/`([^`]+)`/", function ($matches) use ($specialWrap, $specialWrapClass) {
            return "<{$specialWrap} class=\"{$specialWrapClass}\">{$matches[1]}</{$specialWrap}>";
        }, $text);

        // Split the text and wrap each part if splitwrap is set
        if (!empty($splitwrap)) {
            $parts = explode($lineBreakPlaceholder, $text);
            $text = implode("", array_map(function ($index, $part) use ($splitwrap) {
                $part = trim($part);
                return "<{$splitwrap} class=\"prt--" . ($index + 1) . "\">{$part}</{$splitwrap}>";
            }, array_keys($parts), $parts));
        } else {
            // If no splitwrap is set, simply replace placeholder with br tags
            $parts = explode($lineBreakPlaceholder, $text);
            $parts = array_map('trim', $parts);
            $text = implode("<br> ", $parts);
        }

        // Wrap the entire string with the header tag if provided
        if (!empty($headertype)) {
            $headerclassAttr = !empty($headerclass) ? " class=\"$headerclass\"" : "";
            $text = "<{$headertype}{$headerclassAttr}>{$text}</{$headertype}>";
        }

        return $text;
    }
}
