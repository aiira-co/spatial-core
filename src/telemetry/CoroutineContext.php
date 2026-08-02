<?php

declare(strict_types=1);

namespace Spatial\Telemetry;

use OpenTelemetry\Context\Context;
use OpenSwoole\Coroutine as Co;

use function defer;
use function extension_loaded;

/**
 * Keeps OpenTelemetry's active context scoped to the current coroutine.
 *
 * The SDK's default storage tracks PHP fibers, but OpenSwoole's HTTP server
 * schedules coroutines separately. Without an explicit fork/switch/destroy
 * per coroutine, two concurrent requests in one worker share one context
 * stack and parent spans onto each other.
 *
 * Call once at the start of each request, before extracting trace headers or
 * activating a span. A defer hook destroys the fork when the coroutine ends.
 */
final class CoroutineContext
{
    /** Marks this coroutine as already forked in Co::getContext(). */
    private const FORKED_KEY = 'spatial.telemetry.context.forked';

    public static function bind(): void
    {
        if (!extension_loaded('openswoole') || Co::getCid() <= 0) {
            return;
        }

        $context = Co::getContext();

        if (($context[self::FORKED_KEY] ?? false) === true) {
            return;
        }

        $cid     = Co::getCid();
        $storage = Context::storage();

        $storage->fork($cid);
        $storage->switch($cid);
        $context[self::FORKED_KEY] = true;

        defer(static function () use ($cid, $storage): void {
            $storage->destroy($cid);
        });
    }
}
