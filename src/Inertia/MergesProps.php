<?php

namespace Leaf\Inertia;

trait MergesProps
{
    /**
     * Whether the prop should be merged on the client.
     * @var bool
     */
    protected $merge = false;

    /**
     * Whether the merge should be deep.
     * @var bool
     */
    protected $deepMerge = false;

    /**
     * Paths used to match array items when merging.
     * @var array
     */
    protected $matchOn = [];

    public function merge()
    {
        $this->merge = true;

        return $this;
    }

    public function deepMerge()
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    /**
     * Match array items on the given key path(s) when merging.
     * @param string|array $matchOn
     * @return static
     */
    public function matchOn($matchOn)
    {
        $this->matchOn = is_array($matchOn) ? $matchOn : [$matchOn];

        return $this;
    }

    public function shouldMerge()
    {
        return $this->merge;
    }

    public function shouldDeepMerge()
    {
        return $this->deepMerge;
    }

    public function matchesOn()
    {
        return $this->matchOn;
    }
}
