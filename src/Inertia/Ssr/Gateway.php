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

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($page),
                'timeout' => app()->config('inertia.ssrTimeout', 5),
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $response = json_decode($response, true);

        if (!is_array($response) || !isset($response['head'], $response['body'])) {
            return null;
        }

        return new Response(
            implode("\n", $response['head']),
            $response['body']
        );
    }
}
