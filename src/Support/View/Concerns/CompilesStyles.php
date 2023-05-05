<?php

namespace TheFresh\PubSub\Support\View\Concerns;

trait CompilesStyles
{
    /**
     * Compile the conditional style statement into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStyle($expression)
    {
        $expression = is_null($expression) ? '([])' : $expression;

        return "style=\"<?php echo \Illuminate\Support\Arr::toCssStyles{$expression} ?>\"";
    }
}
