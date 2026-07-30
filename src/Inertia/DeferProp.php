<?php

namespace Leaf\Inertia;

/**
 * A prop that is not evaluated on first load. Instead it is advertised
 * to the client in `deferredProps`, and the client fetches it in a
 * follow-up partial reload once the page has rendered.
 */
class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var string
     */
    protected $group;

    public function __construct(callable $callback, string $group = 'default')
    {
        $this->callback = $callback;
        $this->group = $group;
    }

    /**
     * The group this deferred prop is fetched with.
     * @return string
     */
    public function group()
    {
        return $this->group;
    }

    public function __invoke()
    {
        return call_user_func($this->callback);
    }
}
