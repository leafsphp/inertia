<?php

if (!function_exists('inertia')) {
    /**
     * Render Inertia page
     * @param string $component The component to render.
     * @param array $props The props to pass to the component.
     */
    function inertia(string $component, array $props = [])
    {
        return \Leaf\Inertia::render($component, $props);
    }
}
