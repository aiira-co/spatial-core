<?php

declare(strict_types=1);

// Verification for the http.route normalizer, which keeps HTTP metric
// cardinality bounded by the number of routes rather than the number of URLs
// ever requested. Loads the local middleware before the autoloader can supply
// a vendored copy, then exercises the private normalizer via reflection.
//
// Run: php spatial-core/test/route-normalizer-test.php

$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../nx_api/vendor/autoload.php',
    __DIR__ . '/../../../nx_suite_api/vendor/autoload.php',
];

$loaded = false;
foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    fwrite(STDERR, "No autoloader found; run `composer install` in spatial-core.\n");
    exit(2);
}

require __DIR__ . '/../src/telemetry/OpenTelemetryMiddleware.php';

use Spatial\Telemetry\OpenTelemetryMiddleware;

$reflection = new ReflectionClass(OpenTelemetryMiddleware::class);
$instance = $reflection->newInstanceWithoutConstructor();
$normalize = $reflection->getMethod('normalizePath');

$cases = [
    // path                                                    => expected template
    '/'                                                        => '/',
    '/health'                                                  => '/health',
    '/api/v1/users'                                            => '/api/v1/users',
    '/api/v1/users/42'                                         => '/api/v1/users/{id}',
    '/api/v1/users/42/posts/7'                                  => '/api/v1/users/{id}/posts/{id}',
    '/users/3f2504e0-4f89-11d3-9a0c-0305e82c3301'              => '/users/{uuid}',
    '/media/01ARZ3NDEKTSV4RRFFQ69G5FAV'                        => '/media/{ulid}',
    '/files/507f1f77bcf86cd799439011a1b2'                       => '/files/{hash}',
    '/posts/my-great-post-2024-edition'                        => '/posts/{id}',
    '/notifications'                                           => '/notifications',
    '/api/v1/organisations/9/members/3f2504e0-4f89-11d3-9a0c-0305e82c3301'
                                                               => '/api/v1/organisations/{id}/members/{uuid}',
    // trailing slash must not create an empty trailing segment
    '/api/v1/users/'                                           => '/api/v1/users',
];

$failures = 0;

foreach ($cases as $path => $expected) {
    $actual = $normalize->invoke($instance, $path);
    $ok = $actual === $expected;

    if (!$ok) {
        $failures++;
    }

    printf(
        "%s  %-64s -> %s%s\n",
        $ok ? 'ok  ' : 'FAIL',
        $path,
        $actual,
        $ok ? '' : "   (expected {$expected})"
    );
}

// Cardinality proof: 5000 distinct URLs must collapse to a handful of labels.
$templates = [];
for ($i = 0; $i < 5000; $i++) {
    $uuid = sprintf(
        '%08x-%04x-%04x-%04x-%012x',
        $i,
        $i % 0xffff,
        $i % 0xffff,
        $i % 0xffff,
        $i
    );
    $templates[$normalize->invoke($instance, "/api/v1/users/{$uuid}/posts/{$i}")] = true;
    $templates[$normalize->invoke($instance, "/api/v1/orders/{$i}")] = true;
}

printf(
    "\n10000 distinct URLs collapsed to %d metric label(s): %s\n",
    count($templates),
    implode(', ', array_keys($templates))
);

echo $failures === 0
    ? "\nAll " . count($cases) . " normalizer cases passed.\n"
    : "\n{$failures} case(s) FAILED.\n";

exit($failures === 0 ? 0 : 1);
