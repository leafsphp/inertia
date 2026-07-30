<?php

namespace Leaf\Inertia;

/**
 * A prop whose value is merged into the client's existing prop value
 * instead of replacing it (e.g. infinite scroll pagination).
 */
class MergeProp implements Mergeable
{
    use MergesProps;

    /**
     * @var mixed
     */
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
        $this->merge = true;
    }

    public function __invoke()
    {
        return is_callable($this->value) ? call_user_func($this->value) : $this->value;
    }
}
