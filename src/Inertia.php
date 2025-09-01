<?php

namespace Leaf;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

    protected static $sharedProps = [];

    /**
     * Render InertiaJS view
     * 
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
            'props' => array_merge($props, self::getSharedProps()),
            'url' => Str::start(Str::after(
                request()->getUrl() . request()->getPath() . (request()->getQueryString() ? '?' . request()->getQueryString() : ''),
                request()->getUrl()
            ), '/'),
            'version' => static::getVersion(),
        ];

        $page = array_merge($page, self::getSharedPageInfo());

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
     * Add shared props
     */
    public static function share($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                static::$sharedProps[$k] = $v;
            }
        } else {
            static::$sharedProps[$key] = $value;
        }
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
        $userShared = [];

        foreach (static::$sharedProps as $key => $value) {
            $userShared[$key] = is_callable($value) ? $value() : $value;
        }

        $shared = array_merge([
            'session' => null,
            'flash' => null,
            '_token' => null,
            'request' => request()->urlData(),
            'auth' => [
                'id' => null,
                'user' => null,
                'errors' => null,
            ],
            'user' => null,
            'billing' => null,
        ], $userShared);

        if (function_exists('session')) {
            $sessionData = session()->body();

            unset($sessionData['leaf']['flash']);
            unset($sessionData['leaf']['hidden']);
            unset($sessionData['leaf']['encrypted']);

            $shared['session'] = $sessionData;
            $shared['flash'] = flash()->display();
        }

        $user = null;

        if (function_exists('auth')) {
            $user = auth()->user() ? auth()->user()->get() : null;

            $shared['auth'] = [
                'id' => auth()->id(),
                'user' => $user,
                'permissions' => $user ? auth()->user()->permissions() : null,
                'roles' => $user ? auth()->user()->roles() : null,
                'errors' => auth()->errors(),
            ];

            $shared['user'] = $user;

            if (function_exists('billing')) {
                $shared['billing'] = [
                    'tiers' => billing()->tiers(),
                    'periods' => billing()->periods(),
                ];

                $subscription = $user ? auth()->user()->subscription() : [];

                if ($user) {
                    $shared['user'] = $shared['auth']['user'] = array_merge($shared['user'] ?? [], [
                        'hasSubscription' => !!$subscription,
                        'subscription' => $subscription,
                        'isOnTrial' => ($subscription['status'] ?? false) === \Leaf\Billing\Subscription::STATUS_TRIAL,
                    ]);
                }
            }
        }

        if (class_exists('\Leaf\Anchor\CSRF')) {
            $shared['_token'] = csrf()->token();
        }

        return $shared;
    }

    /**
     * Get shared page info
     */
    public static function getSharedPageInfo()
    {
        $shared = [
            'appName' => _env('APP_NAME', 'Leaf App'),
            'appUrl' => _env('APP_URL', request()->getUrl()),
        ];

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
     * 
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
