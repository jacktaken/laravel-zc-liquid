<?php

/**
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid;

use Liquid\Traits\HelpersTrait;

/**
 * Implements a template variable.
 */
class Variable
{

    use HelpersTrait;

    /**
     * @var array The filters to execute on the variable
     */
    private $filters = array();

    /**
     * @var string The name of the variable
     */
    private $name;

    /**
     * @var string The markup of the variable
     */
    protected $markup;

    /**
     * @var LiquidCompiler $compiler
     */
    protected $compiler;

    /**
     * Constructor
     *
     * @param string $markup
     * @param LiquidCompiler $compiler
     */
    public function __construct($markup, LiquidCompiler $compiler)
    {
        $this->compiler = $compiler;
        $this->markup = $markup;

        $quotedFragmentRegexp = new Regexp('/\s*?(' . Constant::QuotedFragmentPartial . ')\s*' . Constant::FilterSeparatorPartial . '?\s*(.*)/ms');
        if($quotedFragmentRegexp->match($markup)) {
            $this->name = $quotedFragmentRegexp->matches[1];
        }

        if(!empty($quotedFragmentRegexp->matches[2])) {
            $filterParserRegexp = new Regexp('/(?:\s+|' . Constant::QuotedFragmentPartial . '|' . Constant::ArgumentSeparator . ')+/m');
            if($filterParserRegexp->matchAll($quotedFragmentRegexp->matches[2])) {
                foreach($filterParserRegexp->matches[0] AS $filter) {
                    $filterNameRegexp = new Regexp('/\s*?(\w+)/');
                    $filterNameRegexp->match($filter);
                    $filtername = $filterNameRegexp->matches[1];

                    $filterArgumentRegexp = new Regexp('/(?:' . Constant::FilterArgumentSeparator . '|' . Constant::ArgumentSeparator . ')\s*((?:\w+\s*\:\s*)?' . Constant::QuotedFragmentPartial . ')/mu');
                    $filterArgumentRegexp->matchAll($filter);

                    //$matches = $this->arrayFlatten(!empty($filterArgumentRegexp->matches[1]) ? $filterArgumentRegexp->matches[1] : array());
                    $matches = $this->arrayFlatten(!empty($filterArgumentRegexp->matches[1]) ? $filterArgumentRegexp->matches[1] : array());
                    $this->filters[] = array($filtername, $matches);
                }
            }
        }

//        $filterSeperatorRegexp = new Regexp('/' . Constant::FilterSeparatorPartial . '\s*(.*)/ms');
//        $filterRegexp = new Regexp('/(?:\s+|' . Constant::QuotedFragmentPartial . '|' . Constant::ArgumentSeparator . ')+/ms');
//        $filterNameRegexp = new Regexp('/\s*?(\w+)/');
//        $filterArgumentRegexp = new Regexp('/(?:' . Constant::FilterArgumentSeparator . '|' . Constant::ArgumentSeparator . ')\s*((?:\w+\s*\:\s*)?' . Constant::QuotedFragmentPartial . ')/ms');
//        if($filterSeperatorRegexp->match($markup)) {
//            if($filterRegexp->matchAll($filterSeperatorRegexp->matches[1])) {
//                foreach($filterRegexp->matches[0] AS $filter) {
//                    $filterNameRegexp->match($filter);
//                    $filtername = $filterNameRegexp->matches[1];
//
//                    $filterArgumentRegexp->matchAll($filter);
//
//                    $matches = $this->arrayFlatten(!empty($filterArgumentRegexp->matches[1]) ? $filterArgumentRegexp->matches[1] : array());
//                    $this->filters[] = array($filtername, $matches);
//                }
//            }
//        }

        if ($this->compiler->getAutoEscape()) {
            // if auto_escape is enabled, and
            // - there's no raw filter, and
            // - no escape filter
            // - no other standard html-adding filter
            // then
            // - add a mandatory escape filter

            $addEscapeFilter = true;

            foreach ($this->filters as $filter) {
                // with empty filters set we would just move along
                if (in_array($filter[0], array('escape', 'escape_once', 'raw', 'newline_to_br'))) {
                    // if we have any raw-like filter, stop
                    $addEscapeFilter = false;
                    break;
                }
            }

            if ($addEscapeFilter) {
                $this->filters[] = array('escape', array());
            }
        }
    }

    /**
     * Gets the variable name
     *
     * @return string The name of the variable
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets all Filters
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Renders the variable with the data in the context
     *
     * @param Context $context
     *
     * @return mixed|string
     * @throws LiquidException
     */
    public function render(Context $context)
    {
        $output = $context->get($this->name);

        $filters = $this->filters;
        if(in_array(trim($this->name), $this->getLayoutVariableNames())) {
            $filters[0] = [];
        }

        foreach ($filters as $filter) {
            if(empty($filter)) {
                continue;
            }

            list($filtername, $filterArgKeys) = $filter;

            $filterArgValues = array();

            foreach ($filterArgKeys as $arg_key) {
                $filterArgValues[] = $context->get($arg_key);
            }

            $output = $context->invoke($filtername, $output, $filterArgValues);
        }

        if (is_float($output)) {
            if ($output == (int)$output) {
                return number_format($output, 1);
            }
        }

        return $output;
    }

    /**
     * @return array
     */
    protected function getLayoutVariableNames()
    {
        $names = ['content_for_layout'];
        if($dinamic = $this->compiler->getLayoutVariableName()) {
            $names[] = $dinamic;
        }

        return array_unique($names);
    }
}
