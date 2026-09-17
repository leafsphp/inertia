<?php

/*
|--------------------------------------------------------------------------
| Test harness for leafs/inertia
|--------------------------------------------------------------------------
| Inertia normally runs inside a Leaf app. Here we simulate the request
| environment through $_SERVER (leafs/http reads it statically), shim
| app()/_env()/render() from leaf core and mvc-core, and provide a
| minimal Leaf\Config so request()/response() become inspectable
| singletons. Inertia's static state is reset before every test.
*/

require __DIR__ . '/shims/Config.php';

if (!function_exists('app')) {
    function app()
    {
        return new class () {
            public function config($key = null, $default = null)
            {
                return $GLOBALS['__appConfig'][$key] ?? $default;
            }
        };
    }
}

if (!function_exists('_env')) {
    function _env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('view')) {
    // mvc-core's view() — Inertia compiles the root shell through it on full
    // page loads and hands the markup to response()->markup() with a status
    function view(string $view, array $data = [])
    {
        $GLOBALS['__renderedView'] = ['view' => $view, 'data' => $data];

        return "<!-- $view -->";
    }
}

if (!function_exists('flash')) {
    // leafs/session's flash bag: display($key) reads a bag once and clears it
    function flash()
    {
        return new class () {
            public function display(string $key = 'message')
            {
                $value = $GLOBALS['__flash'][$key] ?? null;
                unset($GLOBALS['__flash'][$key]);

                return $value;
            }
        };
    }
}

function setInertiaRequest(string $method = 'GET', string $uri = '/', array $headers = []): void
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['QUERY_STRING'] = parse_url($uri, PHP_URL_QUERY) ?? '';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_HOST'] = 'leaf.test';
    $_SERVER['SERVER_NAME'] = 'leaf.test';
    $_SERVER['SERVER_PORT'] = '80';
    $_GET = [];

    foreach (array_keys($_SERVER) as $key) {
        if (strpos($key, 'HTTP_X_INERTIA') === 0) {
            unset($_SERVER[$key]);
        }
    }

    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
}

/** Render and return the decoded page object (X-Inertia JSON response) */
function renderPage(string $component, array $props = [], array $headers = [], int $status = 200): array
{
    setInertiaRequest('GET', $_SERVER['REQUEST_URI'] ?? '/', array_merge(['X-Inertia' => 'true'], $headers));

    ob_start();
    \Leaf\Inertia::render($component, $props, $status);
    $output = ob_get_clean();

    return json_decode($output, true) ?? [];
}

/** The response() singleton, for status/header assertions */
function httpResponse(): \Leaf\Http\Response
{
    return response();
}

function responseStatus(): int
{
    $reflection = new ReflectionProperty(\Leaf\Http\Response::class, 'status');

    return $reflection->getValue(httpResponse());
}

function responseHeaders(): array
{
    $reflection = new ReflectionProperty(\Leaf\Http\Response::class, 'headers');

    return $reflection->getValue(httpResponse());
}

function resetInertiaState(): void
{
    $reflection = new ReflectionClass(\Leaf\Inertia::class);

    foreach ([
        'rootView' => '_inertia',
        'sharedProps' => [],
        'omittedProps' => [],
        'version' => null,
        'encryptHistory' => false,
        'clearHistory' => false,
    ] as $property => $value) {
        $reflection->setStaticPropertyValue($property, $value);
    }
}

uses()->beforeEach(function () {
    \Leaf\Config::reset();
    resetInertiaState();
    setInertiaRequest();
    unset($GLOBALS['__renderedView']);
    $GLOBALS['__appConfig'] = [];
    $GLOBALS['__flash'] = [];
})->in(__DIR__);
