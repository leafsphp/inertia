<?php

use Leaf\Inertia;

test('inertia requests get a json page object with the x-inertia header', function () {
    $page = renderPage('Dashboard', ['count' => 5]);

    expect($page['component'])->toBe('Dashboard');
    expect($page['props']['count'])->toBe(5);
    expect($page['url'])->toBe('/');
    expect($page)->toHaveKeys(['version', 'encryptHistory', 'clearHistory', 'appName', 'appUrl']);
    expect($page['encryptHistory'])->toBeFalse();
    expect($page['clearHistory'])->toBeFalse();

    expect(responseHeaders()['X-Inertia'] ?? null)->toBe('true');
    expect(responseHeaders()['Vary'] ?? null)->toBe('X-Inertia');
    expect(responseStatus())->toBe(200);
});

test('the url includes the query string', function () {
    setInertiaRequest('GET', '/users?page=2', ['X-Inertia' => 'true']);

    ob_start();
    Inertia::render('Users');
    $page = json_decode(ob_get_clean(), true);

    expect($page['url'])->toBe('/users?page=2');
});

test('non-inertia requests render the root view with the page object', function () {
    setInertiaRequest();

    Inertia::render('Home', ['a' => 1]);

    expect($GLOBALS['__renderedView']['view'])->toBe('_inertia');
    expect($GLOBALS['__renderedView']['data']['page']['component'])->toBe('Home');
    expect($GLOBALS['__renderedView']['data']['page']['props']['a'])->toBe(1);
});

test('setRootView changes the root view', function () {
    Inertia::setRootView('app');
    setInertiaRequest();

    Inertia::render('Home');

    expect($GLOBALS['__renderedView']['view'])->toBe('app');
});

test('shared props are merged and page props win', function () {
    Inertia::share('appVersion', '1.0');
    Inertia::share(['locale' => 'en', 'count' => 'shared']);

    $page = renderPage('Dashboard', ['count' => 'page']);

    expect($page['props']['appVersion'])->toBe('1.0');
    expect($page['props']['locale'])->toBe('en');
    expect($page['props']['count'])->toBe('page');
});

test('closure shared props are resolved lazily and flushShared clears them', function () {
    Inertia::share('rand', fn () => 'resolved');

    expect(renderPage('A')['props']['rand'])->toBe('resolved');

    Inertia::flushShared();

    expect(renderPage('A')['props'])->not->toHaveKey('rand');
});

test('closure props are resolved and dot keys are unpacked', function () {
    $page = renderPage('Dashboard', [
        'lazyValue' => fn () => 'computed',
        'meta.title' => 'Hello',
    ]);

    expect($page['props']['lazyValue'])->toBe('computed');
    expect($page['props']['meta']['title'])->toBe('Hello');
});

test('partial reloads honour the only header for the matching component', function () {
    $props = ['a' => 1, 'b' => 2, 'c' => 3];

    $page = renderPage('Dashboard', $props, [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'a,c',
    ]);

    expect($page['props'])->toHaveKeys(['a', 'c']);
    expect($page['props'])->not->toHaveKey('b');
});

test('partial headers are ignored for a different component', function () {
    $page = renderPage('Dashboard', ['a' => 1, 'b' => 2], [
        'X-Inertia-Partial-Component' => 'Other',
        'X-Inertia-Partial-Data' => 'a',
    ]);

    expect($page['props'])->toHaveKeys(['a', 'b']);
});

test('partial reloads honour the except header', function () {
    $page = renderPage('Dashboard', ['a' => 1, 'b' => 2, 'c' => 3], [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Except' => 'b',
    ]);

    expect($page['props'])->toHaveKeys(['a', 'c']);
    expect($page['props'])->not->toHaveKey('b');
});

test('optional props are skipped on first load and resolved when requested', function () {
    $props = ['always' => 1, 'expensive' => Inertia::optional(fn () => 'computed')];

    expect(renderPage('Dashboard', $props)['props'])->not->toHaveKey('expensive');

    $page = renderPage('Dashboard', $props, [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'expensive',
    ]);

    expect($page['props']['expensive'])->toBe('computed');
    expect($page['props'])->not->toHaveKey('always');
});

test('lazy props behave like optional props', function () {
    expect(renderPage('D', ['x' => Inertia::lazy(fn () => 1)])['props'])->not->toHaveKey('x');
});

test('deferred props are advertised by group on first load and resolved on partial reload', function () {
    $props = [
        'stats' => Inertia::defer(fn () => ['visits' => 10]),
        'logs' => Inertia::defer(fn () => ['a'], 'secondary'),
        'title' => 'Dash',
    ];

    $page = renderPage('Dashboard', $props);

    expect($page['props'])->not->toHaveKeys(['stats', 'logs']);
    expect($page['deferredProps'])->toBe(['default' => ['stats'], 'secondary' => ['logs']]);

    $partial = renderPage('Dashboard', $props, [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'stats',
    ]);

    expect($partial['props']['stats'])->toBe(['visits' => 10]);
    expect($partial)->not->toHaveKey('deferredProps');
});

test('always props survive only and except filters', function () {
    $props = [
        'a' => 1,
        'b' => 2,
        'important' => Inertia::always(fn () => 'kept'),
    ];

    $only = renderPage('Dashboard', $props, [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'a',
    ]);

    expect($only['props'])->toHaveKeys(['a', 'important']);
    expect($only['props']['important'])->toBe('kept');

    $except = renderPage('Dashboard', $props, [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Except' => 'important',
    ]);

    expect($except['props']['important'])->toBe('kept');
});

test('merge props resolve their value and set mergeProps meta', function () {
    $page = renderPage('Feed', [
        'items' => Inertia::merge(fn () => [1, 2, 3]),
        'plain' => 'x',
    ]);

    expect($page['props']['items'])->toBe([1, 2, 3]);
    expect($page['mergeProps'])->toBe(['items']);
    expect($page)->not->toHaveKeys(['deepMergeProps', 'matchPropsOn']);
});

test('deep merge and matchOn set their own meta', function () {
    $page = renderPage('Feed', [
        'nested' => Inertia::deepMerge(['a' => [1]]),
        'users' => Inertia::merge([['id' => 1]])->matchOn('id'),
    ]);

    expect($page['deepMergeProps'])->toBe(['nested']);
    expect($page['mergeProps'])->toBe(['users']);
    expect($page['matchPropsOn'])->toBe(['users.id']);
});

test('props named in the reset header are not marked as mergeable', function () {
    $page = renderPage('Feed', [
        'items' => Inertia::merge([1, 2]),
    ], ['X-Inertia-Reset' => 'items']);

    expect($page['props']['items'])->toBe([1, 2]);
    expect($page)->not->toHaveKey('mergeProps');
});

test('deferred mergeable props keep merge meta when resolved', function () {
    $props = ['feed' => Inertia::defer(fn () => [1])->merge()];

    $first = renderPage('Feed', $props);
    expect($first)->not->toHaveKey('mergeProps');

    $partial = renderPage('Feed', $props, [
        'X-Inertia-Partial-Component' => 'Feed',
        'X-Inertia-Partial-Data' => 'feed',
    ]);

    expect($partial['props']['feed'])->toBe([1]);
    expect($partial['mergeProps'])->toBe(['feed']);
});

test('history encryption and clearing are reflected in the page', function () {
    Inertia::encryptHistory();
    Inertia::clearHistory();

    $page = renderPage('Dashboard');

    expect($page['encryptHistory'])->toBeTrue();
    expect($page['clearHistory'])->toBeTrue();
});

test('an explicit version string or resolver is used', function () {
    Inertia::version('v-123');
    expect(renderPage('A')['version'])->toBe('v-123');

    Inertia::version(fn () => 'v-456');
    expect(renderPage('A')['version'])->toBe('v-456');
});

test('a stale asset version returns a 409 with the current url', function () {
    Inertia::version('current');

    setInertiaRequest('GET', '/dashboard', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'stale',
    ]);

    ob_start();
    Inertia::render('Dashboard');
    $output = ob_get_clean();

    expect($output)->toBe('');
    expect(responseStatus())->toBe(409);
    expect(responseHeaders()['X-Inertia-Location'] ?? null)->toBe('/dashboard');
});

test('a matching asset version renders normally', function () {
    Inertia::version('current');

    $page = renderPage('Dashboard', [], ['X-Inertia-Version' => 'current']);

    expect($page['component'])->toBe('Dashboard');
    expect(responseStatus())->toBe(200);
});

test('location returns a 409 with x-inertia-location for inertia requests', function () {
    setInertiaRequest('GET', '/', ['X-Inertia' => 'true']);

    ob_start();
    Inertia::location('https://example.com');
    ob_end_clean();

    expect(responseStatus())->toBe(409);
    expect(responseHeaders()['X-Inertia-Location'] ?? null)->toBe('https://example.com');
});

test('omitted props are removed from shared data', function () {
    Inertia::setOmittedProps(['session', '_token', 'billing']);

    $page = renderPage('Dashboard');

    expect($page['props'])->not->toHaveKeys(['session', '_token', 'billing']);
    expect($page['props'])->toHaveKey('auth');
});

test('the inertia() helper renders a page', function () {
    setInertiaRequest('GET', '/', ['X-Inertia' => 'true']);

    ob_start();
    inertia('Helper', ['ok' => true]);
    $page = json_decode(ob_get_clean(), true);

    expect($page['component'])->toBe('Helper');
    expect($page['props']['ok'])->toBeTrue();
});
