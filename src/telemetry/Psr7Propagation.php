<?php

declare(strict_types=1);

namespace Spatial\Telemetry;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use Psr\Http\Message\RequestInterface;

/**
 * W3C trace context helpers for PSR-7 messages.
 *
 * Incoming: extract traceparent/tracestate from a server request and continue
 * the trace. Outgoing: inject the current span into headers on downstream
 * HTTP calls so service boundaries stay linked.
 */
final class Psr7Propagation
{
    private static ?PropagationGetterInterface $getter = null;
    private static ?PropagationSetterInterface $setter = null;

    public static function extractParent(RequestInterface $request): ContextInterface
    {
        return TraceContextPropagator::getInstance()->extract(
            $request,
            self::getter(),
        );
    }

    /**
     * @param array<string, array<int, string>> $headers
     *
     * @return array<string, array<int, string>>
     */
    public static function injectHeaders(array $headers, ?ContextInterface $context = null): array
    {
        TraceContextPropagator::getInstance()->inject(
            $headers,
            self::setter(),
            $context,
        );

        return $headers;
    }

    public static function injectRequest(RequestInterface $request, ?ContextInterface $context = null): RequestInterface
    {
        $headers = self::injectHeaders($request->getHeaders(), $context);

        foreach ($headers as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        return $request;
    }

    private static function getter(): PropagationGetterInterface
    {
        return self::$getter ??= new readonly class implements PropagationGetterInterface {
            public function keys(mixed $carrier): array
            {
                /** @var RequestInterface $carrier */
                return array_map('strtolower', array_keys($carrier->getHeaders()));
            }

            public function get(mixed $carrier, string $key): ?string
            {
                /** @var RequestInterface $carrier */
                $values = $carrier->getHeader($key);

                return $values[0] ?? null;
            }
        };
    }

    private static function setter(): PropagationSetterInterface
    {
        return self::$setter ??= new readonly class implements PropagationSetterInterface {
            public function set(mixed &$carrier, string $key, string $value): void
            {
                /** @var array<string, array<int, string>> $carrier */
                $carrier[$key] = [$value];
            }
        };
    }
}
