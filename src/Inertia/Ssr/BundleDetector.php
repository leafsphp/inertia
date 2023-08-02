<?php

namespace Leaf\Inertia\Ssr;

class BundleDetector
{
    public function detect()
    {
        return collect([
            app()->config('inertia.ssrBundle'),
            // base_path('bootstrap/ssr/ssr.mjs'),
            // base_path('bootstrap/ssr/ssr.js'),
            PublicPath('js/ssr.js'),
        ])->filter()->first(function ($path) {
            return file_exists($path);
        });
    }
}
