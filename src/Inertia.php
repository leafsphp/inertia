<?php

namespace Leaf;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Leaf\Inertia\AlwaysProp;
use Leaf\Inertia\DeferProp;
use Leaf\Inertia\IgnoreFirstLoad;
use Leaf\Inertia\LazyProp;
use Leaf\Inertia\MergeProp;
use Leaf\Inertia\Mergeable;
use Leaf\Inertia\OptionalProp;

/**
 * Inertia Adapter for Leaf
 * ----
 * This adapter allows you to use InertiaJS with Leaf. It mirrors the
 * feature set of inertia-laravel v2: partial reloads (only/except),
 * optional/deferred/always/mergeable props, history encryption and
 * external redirects.
 */
class Inertia
{
    /**
     * Root view
     */
    protected static $rootView = '_inertia';

    protected static $sharedProps = [];

    protected static $omittedProps = [];

    /**
     * Explicit asset version (string or resolver callable)
     * @var string|callable|null
     */
    protected static $version;

    /**
     * @var bool
     */
    protected static $encryptHistory = false;

    /**
     * @var bool
     */
    protected static $clearHistory = false;

    /**
     * Create a prop that is only evaluated when requested in a partial
     * reload. Never included on first load.
     */
    public static function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Create an optional prop.
     * @deprecated Use Inertia::optional() instead.
     */
    public static function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create a prop that is fetched by the client in a follow-up request
     * after the page first renders. Props in the same group are fetched
     * together.
     */
    public static function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    /**
     * Create a prop that is included in every response, even when a
     * partial reload does not request it.
     */
    public static function always($value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Create a prop that the client merges into its existing value
     * instead of overwriting it.
     */
    public static function merge($value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a prop that the client deep-merges into its existing value.
     */
    public static function deepMerge($value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Encrypt the client's history entry for this page (and subsequent
     * pages until turned off).
     */
    public static function encryptHistory(bool $encrypt = true)
    {
        static::$encryptHistory = $encrypt;
    }

    /**
     * Instruct the client to clear its history state (e.g. after logout).
     */
    public static function clearHistory(bool $clear = true)
    {
        static::$clearHistory = $clear;
    }

    /**
     * Redirect to an external or non-Inertia URL. For Inertia requests
     * this returns a 409 with an X-Inertia-Location header so the client
     * performs a full page visit.
     */
    public static function location(string $url)
    {
        if (request()->headers('X-Inertia')) {
            return response()
                ->withHeader('X-Inertia-Location', $url)
                ->plain('', 409);
        }

        return response()->redirect($url, 302, false);
    }

    /**
     * Set the asset version, either as a string or a resolver.
     * @param string|callable $version
     */
    public static function version($version)
    {
        static::$version = $version;
    }

    /**
     * Render InertiaJS view
     *
     * @param string $component The component to render.
     * @param array $props The props to pass to the component.
     */
    public static function render(string $component, array $props = [])
    {
        if (function_exists('crash')) {
            crash()->leaveCrumb("inertia: $component", 'view', [], false);
        }

        $version = static::getVersion();

        if (
            request()->headers('X-Inertia') &&
            strtoupper(request()->getMethod()) === 'GET' &&
            request()->headers('X-Inertia-Version', false) !== null &&
            request()->headers('X-Inertia-Version', false) !== $version
        ) {
            return response()
                ->withHeader('X-Inertia-Location', static::currentUrl())
                ->plain('', 409);
        }

        $props = array_merge(static::getSharedProps(), $props);

        $isPartial = request()->headers('X-Inertia-Partial-Component', false) === $component;

        $deferredProps = $isPartial ? [] : static::resolveDeferredProps($props);

        $props = $isPartial
            ? static::resolvePartialProps($props)
            : array_filter($props, fn ($prop) => !($prop instanceof IgnoreFirstLoad));

        $mergeMeta = static::resolveMergeProps($props);

        $props = static::resolvePropertyInstances($props);

        $page = array_merge(
            [
                'component' => $component,
                'props' => $props,
                'url' => static::currentUrl(),
                'version' => $version,
                'encryptHistory' => static::$encryptHistory,
                'clearHistory' => static::$clearHistory,
            ],
            $deferredProps !== [] ? ['deferredProps' => $deferredProps] : [],
            $mergeMeta,
            static::getSharedPageInfo()
        );

        if (request()->headers('X-Inertia')) {
            return response()
                ->withHeader(['X-Inertia' => 'true', 'Vary' => 'X-Inertia'])
                ->json($page, 200);
        }

        if (function_exists('render')) {
            return render(static::$rootView, compact('page'));
        }

        if (class_exists('Leaf\Blade')) {
            $cachePath = app()->config('views.cache') ?? (getcwd() . '/storage/cache');

            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            $blade = new \Leaf\Blade();
            $blade->configure(
                app()->config('views.path') ?? getcwd(),
                $cachePath
            );

            return response()->markup($blade->render(static::$rootView, compact('page')));
        }

        $engine = new \Leaf\BareUI();
        $engine->config('path', app()->config('views.path') ?? getcwd());

        return response()->markup($engine->render(static::$rootView, compact('page')));
    }

    /**
     * The URL for the current request, path + query string.
     */
    public static function currentUrl(): string
    {
        return Str::start(Str::after(
            request()->getUrl() . request()->getPath() . (request()->getQueryString() ? '?' . request()->getQueryString() : ''),
            request()->getUrl()
        ), '/');
    }

    /**
     * Filter props for a partial reload using the X-Inertia-Partial-Data
     * (only) and X-Inertia-Partial-Except (except) headers. Always props
     * survive both filters.
     */
    protected static function resolvePartialProps(array $props): array
    {
        $only = array_filter(explode(',', request()->headers('X-Inertia-Partial-Data', false) ?? ''));
        $except = array_filter(explode(',', request()->headers('X-Inertia-Partial-Except', false) ?? ''));

        $result = $props;

        if ($only) {
            $result = [];

            foreach ($only as $key) {
                Arr::set($result, $key, Arr::get($props, $key));
            }
        }

        if ($except) {
            Arr::forget($result, $except);
        }

        foreach ($props as $key => $value) {
            if ($value instanceof AlwaysProp) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Collect deferred prop names grouped by their defer group. Deferred
     * props are advertised to the client on first load and requested in
     * follow-up partial reloads.
     */
    protected static function resolveDeferredProps(array $props): array
    {
        $groups = [];

        foreach ($props as $key => $value) {
            if ($value instanceof DeferProp) {
                $groups[$value->group()][] = $key;
            }
        }

        return $groups;
    }

    /**
     * Build the mergeProps/deepMergeProps/matchPropsOn page meta for the
     * props being sent. Props named in the X-Inertia-Reset header are
     * excluded so the client replaces them instead.
     */
    protected static function resolveMergeProps(array $props): array
    {
        $reset = array_filter(explode(',', request()->headers('X-Inertia-Reset', false) ?? ''));

        $mergeProps = [];
        $deepMergeProps = [];
        $matchPropsOn = [];

        foreach ($props as $key => $value) {
            if (!($value instanceof Mergeable) || !$value->shouldMerge() || in_array($key, $reset)) {
                continue;
            }

            if ($value->shouldDeepMerge()) {
                $deepMergeProps[] = $key;
            } else {
                $mergeProps[] = $key;
            }

            foreach ($value->matchesOn() as $path) {
                $matchPropsOn[] = "$key.$path";
            }
        }

        return array_merge(
            $mergeProps !== [] ? ['mergeProps' => $mergeProps] : [],
            $deepMergeProps !== [] ? ['deepMergeProps' => $deepMergeProps] : [],
            $matchPropsOn !== [] ? ['matchPropsOn' => $matchPropsOn] : []
        );
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
     * Forget all shared props
     */
    public static function flushShared()
    {
        static::$sharedProps = [];
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
            $userShared[$key] = $value instanceof Closure ? $value() : $value;
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

        $omitSession = in_array('session', static::$omittedProps);
        $omitAuth = in_array('auth', static::$omittedProps);
        $omitToken = in_array('_token', static::$omittedProps);

        if (function_exists('session') && !$omitSession) {
            $sessionData = session()->body();

            unset($sessionData['leaf']['flash']);
            unset($sessionData['leaf']['hidden']);
            unset($sessionData['leaf']['encrypted']);

            $shared['session'] = $sessionData;
            $shared['flash'] = flash()->display();
        }

        $user = null;

        if (function_exists('auth') && !$omitAuth) {
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

        if (class_exists('\Leaf\Anchor\CSRF') && !$omitToken) {
            $shared['_token'] = csrf()->token();
        }

        foreach (static::$omittedProps as $prop) {
            unset($shared[$prop]);
        }

        return $shared;
    }

    /**
     * Get shared page info
     */
    public static function getSharedPageInfo()
    {
        return [
            'appName' => _env('APP_NAME', 'Leaf App'),
            'appUrl' => _env('APP_URL', request()->getUrl()),
        ];
    }

    /**
     * Set omitted props
     */
    public static function setOmittedProps(array $omittedProps)
    {
        static::$omittedProps = $omittedProps;
    }

    /**
     * Get version
     */
    public static function getVersion()
    {
        if (static::$version !== null) {
            $version = static::$version;

            return (string) (is_callable($version) && !is_string($version) ? $version() : $version);
        }

        $isBladeProject = static::isBladeProject();
        $ext = $isBladeProject ? 'blade' : 'view';

        $versionFile = app()->config('inertia.version') ?? ((app()->config('views.path') ?? getcwd()) . "/_inertia.$ext.php");

        return file_exists($versionFile) ? md5_file($versionFile) : '';
    }

    public static function isBladeProject()
    {
        $directory = getcwd();
        $isBladeProject = false;

        if (file_exists("$directory/config/view.php")) {
            $viewConfig = require "$directory/config/view.php";
            $isBladeProject = strpos(strtolower($viewConfig['viewEngine'] ?? $viewConfig['view_engine'] ?? ''), 'blade') !== false;
        } elseif (file_exists("$directory/composer.lock")) {
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
            if ($value instanceof Closure) {
                $value = $value();
            }

            if (
                $value instanceof OptionalProp
                || $value instanceof DeferProp
                || $value instanceof AlwaysProp
                || $value instanceof MergeProp
            ) {
                $value = $value();
            }

            if (is_array($value)) {
                $value = static::resolvePropertyInstances($value, false);
            }

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
