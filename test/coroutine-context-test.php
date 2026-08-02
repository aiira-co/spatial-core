<?php

declare(strict_types=1);

/**
 * Verifies that OpenTelemetry contexts stay isolated when the storage is
 * forked per coroutine, which is what CoroutineContext::bind() arranges.
 *
 * Run: php spatial-core/test/coroutine-context-test.php
 */

$autoloaders = [
    __DIR__ . '/../../../nx_api/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}

require __DIR__ . '/../src/telemetry/CoroutineContext.php';
require __DIR__ . '/../src/telemetry/Psr7Propagation.php';

use GuzzleHttp\Psr7\ServerRequest;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorageScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\NoopSpanProcessor;
use Spatial\Telemetry\Psr7Propagation;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, $ok || $detail === '' ? '' : "  ({$detail})");
}

/** @return array{0: object, 1: ContextStorageScopeInterface} */
function startServerSpan(object $tracer, string $name): array
{
    $span  = $tracer->spanBuilder($name)->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
    $scope = $span->activate();

    return [$span, $scope];
}

echo "== 1. Forked contexts do not leak spans between coroutines ==\n";

$tracer = (new TracerProvider(new NoopSpanProcessor()))->getTracer('test');
$storage = Context::storage();

$storage->fork(1);
$storage->switch(1);
[$spanA, $scopeA] = startServerSpan($tracer, 'request-a');
$spanIdA = $spanA->getContext()->getSpanId();

$storage->fork(2);
$storage->switch(2);
[$spanB, $scopeB] = startServerSpan($tracer, 'request-b');
$spanIdB = $spanB->getContext()->getSpanId();

check('two concurrent spans have distinct span ids', $spanIdA !== '' && $spanIdB !== '' && $spanIdA !== $spanIdB);

$storage->switch(2);
$currentInB = Span::fromContext(Context::getCurrent())->getContext()->getSpanId();
check('coroutine 2 still sees its own span', $currentInB === $spanIdB, "got={$currentInB}");

$storage->switch(1);
$currentInA = Span::fromContext(Context::getCurrent())->getContext()->getSpanId();
check('coroutine 1 still sees its own span', $currentInA === $spanIdA, "got={$currentInA}");

$storage->switch(2);
$scopeB->detach();
$spanB->end();
$storage->destroy(2);

$storage->switch(1);
$scopeA->detach();
$spanA->end();
$storage->destroy(1);

echo "\n== 2. W3C traceparent continues an incoming trace ==\n";

$traceId = str_repeat('a', 32);
$spanId  = str_repeat('b', 16);
$header  = sprintf('00-%s-%s-01', $traceId, $spanId);

$request = new ServerRequest('GET', '/health', ['traceparent' => [$header]]);
$extracted = Psr7Propagation::extractParent($request);
$parent = Span::fromContext($extracted)->getContext();

check('extracted trace id matches the header', $parent->getTraceId() === $traceId);
check('extracted parent span id matches the header', $parent->getSpanId() === $spanId);
check('extracted context is marked sampled', ($parent->getTraceFlags() & 0x01) === 0x01);

$child = $tracer->spanBuilder('child')
    ->setParent($extracted)
    ->startSpan();
check('child span shares the incoming trace id', $child->getContext()->getTraceId() === $traceId);
check('child span has a new span id', $child->getContext()->getSpanId() !== $spanId);
$child->end();

echo "\n== 3. Outbound inject writes traceparent into headers ==\n";

$active = $tracer->spanBuilder('outbound-parent')->startSpan();
$scope  = $active->activate();
$headers = Psr7Propagation::injectHeaders([]);
$scope->detach();
$active->end();

check('traceparent header was injected', isset($headers['traceparent'][0]));
check(
    'injected header carries the active trace id',
    str_contains($headers['traceparent'][0], $active->getContext()->getTraceId())
);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
