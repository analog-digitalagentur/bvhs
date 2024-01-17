<?php
namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * PhoneLinkViewHelper formats a phone number and creates a valid tel: link.
 * It removes spaces and other formatting characters to create a valid tel URI.
 *
 * Example usage:
 * <b:phoneLink number="+49 60 74 89 01-58" prefix="Phone: " />
 *
 * This will output:
 * <a href="tel:+496074890158">Phone: +49 60 74 89 01-58</a>
 *
 * Arguments:
 * - number (string, required): The phone number to format.
 * - prefix (string, optional): A prefix to add before the phone number.
 */
class PhoneLinkViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        $this->registerArgument('number', 'string', 'The phone number to format', true);
        $this->registerArgument('prefix', 'string', 'A prefix to add before the phone number', false, '');
    }

    /**
     * Renders the phone link.
     *
     * @return string Rendered phone link
     */
    public function render(): string
    {
        $number = $this->arguments['number'];
        $prefix = $this->arguments['prefix'];

        // Entfernen von Leerzeichen, Bindestrichen und anderen Nicht-Ziffern-Zeichen, außer dem ersten Pluszeichen
        $cleanNumber = preg_replace('/[^\d]/', '', $number);
        if (substr($number, 0, 1) === '+') {
            $cleanNumber = '+' . $cleanNumber;
        }

        // Formatierten Telefonlink zurückgeben
        return sprintf('<a href="tel:%s">%s%s</a>', $cleanNumber, $prefix, $number);
    }

}
