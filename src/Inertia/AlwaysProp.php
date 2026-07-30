<?php

namespace Leaf\Inertia;

/**
 * A prop that is included in every response, even when a partial
 * reload does not request it (or excludes it).
 */
class AlwaysProp
{
    /**
     * @var mixed
     */
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return is_callable($this->value) ? call_user_func($this->value) : $this->value;
    }
}
