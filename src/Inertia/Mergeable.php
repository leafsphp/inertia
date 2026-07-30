<?php

namespace Leaf\Inertia;

interface Mergeable
{
    /**
     * Mark the prop to be merged on the client instead of overwritten.
     * @return static
     */
    public function merge();

    /**
     * Mark the prop to be deep-merged on the client.
     * @return static
     */
    public function deepMerge();

    /**
     * Should this prop be merged on the client?
     * @return bool
     */
    public function shouldMerge();

    /**
     * Should this prop be deep merged on the client?
     * @return bool
     */
    public function shouldDeepMerge();

    /**
     * Paths used to match items when merging arrays of objects.
     * @return array
     */
    public function matchesOn();
}
