<?php

if (!function_exists('inertia')) {
    /**
     * Render Inertia page
     * @param string $component The component to render.
     * @param array $props The props to pass to the component.
     * @param int $status The HTTP status of the response (eg. 404 for a not-found page).
     */
    function inertia(string $component, array $props = [], int $status = 200)
    {
        return \Leaf\Inertia::render($component, $props, $status);
    }
}
