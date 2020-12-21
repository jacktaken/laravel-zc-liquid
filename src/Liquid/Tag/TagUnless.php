<?php

/**
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid\Tag;

use Liquid\AbstractBlock;
use Liquid\Context;
use Liquid\LiquidCompiler;
use Liquid\LiquidException;
use Liquid\Regexp;
use Liquid\Traits\DecisionTrait;
use Liquid\Traits\HelpersTrait;

/**
 * An if statement
 *
 * Example:
 *
 *     {% unless true %} YES {% else %} NO {% endunless %}
 *
 *     will return:
 *     NO
 */
class TagUnless extends AbstractBlock
{

    use DecisionTrait, HelpersTrait;

    /**
     * Array holding the nodes to render for each logical block
     *
     * @var array
     */
    private $nodelistHolders = array();

    /**
     * Array holding the block type, block markup (conditions) and block nodelist
     *
     * @var array
     */
    protected $blocks = array();

    /**
     * Constructor
     *
     * @param string $markup
     * @param array $tokens
     * @param LiquidCompiler|null $compiler
     */
    public function __construct($markup, array &$tokens, LiquidCompiler $compiler = null)
    {
        $this->nodelist = &$this->nodelistHolders[count($this->blocks)];

        array_push($this->blocks, array('unless', $markup, &$this->nodelist));

        parent::__construct($markup, $tokens, $compiler);
    }

    /**
     * Handler for unknown tags, handle else tags
     *
     * @param string $tag
     * @param array $params
     * @param array $tokens
     * @param int $line
     * @throws LiquidException
     * @throws \ReflectionException
     */
    public function unknownTag($tag, $params, array $tokens, $line = 0)
    {
        if ($tag == 'else') {
            // Update reference to nodelistHolder for this block
            $this->nodelist = &$this->nodelistHolders[count($this->blocks) + 1];
            $this->nodelistHolders[count($this->blocks) + 1] = array();

            array_push($this->blocks, array($tag, $params, &$this->nodelist));

        } else {
            parent::unknownTag($tag, $params, $tokens, $line);
        }
    }

    /**
     * Render the tag
     *
     * @param Context $context
     *
     * @throws \Liquid\LiquidException
     * @return string
     */
    public function render(Context $context)
    {
        $context->push();

        $logicalRegex = new Regexp('/\s+(and|or)\s+/');
        $conditionalRegex = new Regexp('/(' . LiquidCompiler::QUOTED_FRAGMENT . ')\s*([=!<>a-z_]+)?\s*(' . LiquidCompiler::QUOTED_FRAGMENT . ')?/');

        $result = '';
        foreach ($this->blocks as $block) {
            if ($block[0] == 'else') {
                $result = $this->renderAll($block[2], $context);

                break;
            }

            if ($block[0] == 'unless') {
                // Extract logical operators
                $logicalRegex->matchAll($block[1]);

                $logicalOperators = $logicalRegex->matches;
                $logicalOperators = array_merge(array('and'), $logicalOperators[1]);
                // Extract individual conditions
                $temp = $logicalRegex->split($block[1]);

                $conditions = array();

                foreach ($temp as $condition) {
                    if ($conditionalRegex->match($condition)) {
                        $left = (isset($conditionalRegex->matches[1])) ? $conditionalRegex->matches[1] : null;
                        $operator = (isset($conditionalRegex->matches[2])) ? $conditionalRegex->matches[2] : null;
                        $right = (isset($conditionalRegex->matches[3])) ? $conditionalRegex->matches[3] : null;

                        array_push($conditions, array(
                            'left' => $left,
                            'operator' => $operator,
                            'right' => $right
                        ));
                    } else {
                        throw new LiquidException("Syntax Error in tag 'if' - Valid syntax: if [condition]");
                    }
                }

                $boolean = true;
                $results = array();
                foreach ($logicalOperators as $k => $logicalOperator) {
                    $r = $this->interpretCondition($conditions[$k]['left'], $conditions[$k]['right'], $conditions[$k]['operator'], $context);
                    if ($logicalOperator == 'and') {
                        $boolean = $boolean && $this->isTruthy($r);
                    } else {
                        $results[] = $boolean;
                        $boolean = $this->isTruthy($r);
                    }
                }

                $results[] = $boolean;

                if (!in_array(true, $results)) {
                    $result = $this->renderAll($block[2], $context);
                    break;
                }
            }
        }

        $context->pop();

        return $result;
    }
}