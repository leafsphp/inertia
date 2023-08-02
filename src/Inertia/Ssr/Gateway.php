<?php

namespace Leaf\Inertia\Ssr;

class Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page)
    {
        if (!app()->config('inertia.ssrEnabled', true) || !(new BundleDetector())->detect()) {
            return null;
        }

        $url = str_replace('/render', '', app()->config('inertia.ssrUrl', 'http://127.0.0.1:13714')) . '/render';

        try {
            $response = (\Leaf\Fetch::post($url, $page))->data;
        } catch (\Exception $e) {
            return null;
        }

        if (is_null($response)) {
            return null;
        }

        return new Response(
            implode("\n", $response['head']),
            $response['body']
        );
    }
}
