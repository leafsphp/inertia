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
    protected static $rootView = '_inertia';

    /**
     * Render InertiaJS view
     * @param string $component The component to render.
     * @param array $props The props to pass to the component.
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

        if (function_exists('render')) {
            return render(static::$rootView, compact('page'));
        }

        if (class_exists('Leaf\Blade')) {
            $blade = new \Leaf\Blade;
            $blade->configure(
                app()->config('views.path') ?? getcwd(),
                app()->config('views.cache') ?? getcwd()
            );
            return response()->markup($blade->render(static::$rootView, compact('page')));
        }

        $engine = new \Leaf\BareUI;
        $engine->config('path', app()->config('views.path') ?? getcwd());
        return response()->markup($engine->render(static::$rootView, compact('page')));
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
        $isBladeProject = static::isBladeProject();
        $ext = $isBladeProject ? 'blade' : 'view';

        return md5_file(
            app()->config('inertia.version') ?? ((app()->config('views.path') ?? getcwd()) . "/_inertia.$ext.php")
        );
    }

    public static function isBladeProject()
    {
        $directory = getcwd();
        $isBladeProject = false;

        if (file_exists("$directory/config/view.php")) {
            $viewConfig = require "$directory/config/view.php";
            $isBladeProject = strpos(strtolower($viewConfig['viewEngine'] ?? $viewConfig['view_engine'] ?? ''), 'blade') !== false;
        } else if (file_exists("$directory/composer.lock")) {
            $composerLock = json_decode(file_get_contents("$directory/composer.lock"), true);
            $packages = $composerLock['packages'] ?? [];
            foreach ($packages as $package) {
                if ($package['name'] === 'leafs/blade') {
                    $isBladeProject = true;
                    break;
                }
            }
        }

        return $isBladeProject;
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
