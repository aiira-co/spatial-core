<?php

declare(strict_types=1);

namespace Spatial\Telemetry\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Builds Guzzle clients that propagate the active trace and record CLIENT spans.
 */
final class GuzzleClientFactory
{
    /**
     * @param array<string, mixed> $config Guzzle client configuration.
     */
    public static function create(array $config = []): ClientInterface
    {
        $stack = $config['handler'] ?? HandlerStack::create();
        unset($config['handler']);

        if (!$stack instanceof HandlerStack) {
            $stack = HandlerStack::create($stack);
        }

        $stack->push(self::middleware(), 'spatial_otel_http');

        return new Client(['handler' => $stack] + $config);
    }

    public static function middleware(): callable
    {
        return static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler) {
                $prepared = OutboundHttpTracing::begin($request);

                return $handler($prepared->request, $options)->then(
                    static fn ($response) => OutboundHttpTracing::succeed($prepared, $response),
                    static function ($reason) use ($prepared) {
                        if ($reason instanceof Throwable) {
                            OutboundHttpTracing::fail($prepared, $reason);
                        } else {
                            $prepared->close();
                        }

                        return Create::rejectionFor($reason);
                    },
                );
            };
        };
    }
}
