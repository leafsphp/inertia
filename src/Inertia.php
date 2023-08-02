<?php

namespace Leaf;

use Illuminate\Support\Arr;

/**
 * Inertia Adapter for Leaf
 * ----
 * This adapter allows you to use InertiaJS with Leaf.
 */
class Inertia
{
    /**
     * Root view
     */
    protected static $rootView = "_inertia";

    /**
     * Render InertiaJS view
     */
    public static function render(string $component, array $props = [])
    {
        $only = array_filter(explode(',', request()->headers('X-Inertia-Partial-Data', false) ?? ''));

        if ($only && request()->headers('X-Inertia-Partial-Component', false) === $component) {
            $props = Arr::only($props, $only);
        }

        $props = static::resolvePropertyInstances($props);

        $page = [
            'component' => $component,
            'props' => $props,
            'url' => app()->getRoute()['path'] ?? $_SERVER['REQUEST_URI'] ?? '/',
            'version' => static::getVersion(),
        ];

        $page = array_merge($page, self::getSharedProps());

        if (request()->headers('X-Inertia')) {
            return response()->withHeader(['X-Inertia' => 'true'])->json($page, 200);
        }

        render(static::$rootView, compact("page"));
    }

    /**
     * Set root view
     */
    public static function setRootView(string $rootView)
    {
        static::$rootView = $rootView;
    }

    /**
     * Get shared props
     */
    public static function getSharedProps()
    {
        $shared = [
            'session' => null,
            'errors' => null,
            'flash' => null,
            'auth' => [
                'user' => null,
                'errors' => null,
            ],
        ];

        if (app()->config('session.instance')) {
            $shared['session'] = session()->body();
            $shared['flash'] = flash()->display();
        }

        if (app()->config('db.instance')) {
            $shared['auth'] = [
                'user' => auth()->user(),
                'errors' => auth()->errors(),
            ];
        }

        return $shared;
    }

    /**
     * Get version
     */
    public static function getVersion()
    {
        return md5_file(
            app()->config('inertia.version') ?? app()->config('views.path') . "/_inertia.view.php"
        );
    }

    /**
     * Resolve all necessary class instances in the given props.
     * @param array $props The props to resolve.
     * @param bool $unpackDotProps Whether to unpack dot props.
     */
    public static function resolvePropertyInstances(array $props, bool $unpackDotProps = true): array
    {
        foreach ($props as $key => $value) {
            if ($unpackDotProps && str_contains($key, '.')) {
                Arr::set($props, $key, $value);
                unset($props[$key]);
            } else {
                $props[$key] = $value;
            }
        }

        return $props;
    }
}
