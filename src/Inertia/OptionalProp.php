<?php

namespace Leaf\Inertia;

/**
 * A prop that is skipped on first load and only evaluated when
 * requested via a partial reload (`only`).
 */
class OptionalProp implements IgnoreFirstLoad
{
    /**
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke()
    {
        return call_user_func($this->callback);
    }
}
