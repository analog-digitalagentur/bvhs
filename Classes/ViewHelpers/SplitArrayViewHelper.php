<?php

namespace Bo\Bvhs\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * SplitArrayViewHelper splits an array into multiple stacks.
 *
 * Example usage:
 * <b:splitArray array="{myArray}" splitinto="3" smalleststack="3" as="menu">
 *   <f:debug>{menu}</f:debug>
 * </b:splitArray>
 *
 * Arguments:
 * - array (array, required): The input array to be split.
 * - splitinto (int, optional, default=2): The number of stacks to split the array into.
 * - smalleststack (int, optional, default=-1): The index of the stack with the lowest item count. If set to -1, all stacks will have the same item count.
 * - as (string, required): The name of the variable to store the resulting split array, which can be used within the ViewHelper's scope.
 */

class SplitArrayViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('array', 'array', 'The array to split', true);
        $this->registerArgument('splitinto', 'int', 'Number of stacks to split the array into', false, 2);
        $this->registerArgument('smalleststack', 'int', 'The stack with the lowest item count', false, -1);
        $this->registerArgument('as', 'string', 'The name of the variable to store the result', true);
    }

    public function render()
    {
        $array = $this->arguments['array'];
        $splitInto = $this->arguments['splitinto'];
        $smallestStack = $this->arguments['smalleststack'];
        $as = $this->arguments['as'];

        $splitArray = [];

        if (is_array($array) && $array !== []) {
            $splitArray = self::splitArray($array, $splitInto, $smallestStack);
        }

        $this->renderingContext->getVariableProvider()->add($as, $splitArray);
        $output = $this->renderChildren();
        $this->renderingContext->getVariableProvider()->remove($as);

        return $output;
    }

    protected static function splitArray(array $array, int $splitInto, int $smallestStack): array
    {
        $result = [];

        $arraySize = count($array);
        $stackSize = (int) ceil($arraySize / $splitInto);
        $smallerStackSize = $stackSize - 1;

        for ($i = 0; $i < $splitInto; $i++) {
            if ($smallestStack === $i && $smallestStack !== -1) {
                $result[] = array_splice($array, 0, $smallerStackSize);
            } else {
                $result[] = array_splice($array, 0, $stackSize);
            }
        }

        return $result;
    }
}
