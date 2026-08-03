<?php

declare(strict_types=1);

/**
 * Request-scoped Injectable + ScopedContainer — no OpenSwoole required.
 *
 * Run: php test/injectable-scope-test.php
 */

$vendor = dirname(__DIR__, 3) . '/nx_api/vendor/autoload.php';
if (!is_file($vendor)) {
    $vendor = dirname(__DIR__) . '/vendor/autoload.php';
}
require $vendor;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Spatial\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/core/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use Spatial\Core\Attributes\Injectable;
use Spatial\Core\DI\InjectableScope;
use Spatial\Core\DI\ScopedContainer;

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL  {$msg}\n");
        exit(1);
    }
    echo "ok    {$msg}\n";
}

#[Injectable('root')]
class RootService
{
}

#[Injectable('request')]
class RequestService
{
}

#[Injectable('any')]
class AnyService
{
}

echo "== InjectableScope ==\n";
assertTrue(InjectableScope::isRequestScoped('request'), 'request is request-scoped');
assertTrue(InjectableScope::isRequestScoped('any'), 'any is request-scoped');
assertTrue(InjectableScope::isRequestScoped('REQUEST'), 'REQUEST is request-scoped');
assertTrue(!InjectableScope::isRequestScoped('root'), 'root is not request-scoped');
assertTrue(!InjectableScope::isRequestScoped(null), 'null is not request-scoped');

echo "\n== ScopedContainer ==\n";
$c = new ScopedContainer();
$c->markRequestScoped(RequestService::class);
$c->markRequestScoped(AnyService::class);

$root1 = $c->get(RootService::class);
$root2 = $c->get(RootService::class);
assertTrue($root1 === $root2, 'root provider is a singleton');

$c->beginRequest();
$req1 = $c->get(RequestService::class);
$req2 = $c->get(RequestService::class);
assertTrue($req1 === $req2, 'same request returns the same request-scoped instance');
$any1 = $c->get(AnyService::class);
assertTrue($any1 instanceof AnyService, 'any alias resolves');
$c->endRequest();

$c->beginRequest();
$req3 = $c->get(RequestService::class);
assertTrue($req1 !== $req3, 'new request gets a fresh request-scoped instance');
$c->endRequest();

echo "\nAll injectable scope checks passed.\n";
