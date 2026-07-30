<?php

namespace Leaf\Inertia\Ssr;

class BundleDetector
{
    public function detect()
    {
        $candidates = array_filter([
            app()->config('inertia.ssrBundle'),
            function_exists('PublicPath') ? PublicPath('js/ssr.js') : null,
            getcwd() . '/public/js/ssr.js',
        ]);

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
