<?php

declare(strict_types=1);

namespace Spatial\Telemetry\Http;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatial\Telemetry\CoroutineContext;
use Spatial\Telemetry\Psr7Propagation;
use Throwable;

/**
 * Shared outbound HTTP tracing: W3C inject plus a CLIENT span.
 *
 * Used by the Guzzle middleware and by coroutine-native PSR-18 clients so
 * every service gets the same propagation without copying span logic.
 */
final class OutboundHttpTracing
{
    public static function begin(RequestInterface $request): PreparedOutboundRequest
    {
        CoroutineContext::bind();

        $request = Psr7Propagation::injectRequest($request);
        $uri     = $request->getUri();

        $span = Globals::tracerProvider()
            ->getTracer('spatial.http.client')
            ->spanBuilder($request->getMethod() . ' ' . self::spanName($request))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.request.method', $request->getMethod())
            ->setAttribute('server.address', $uri->getHost())
            ->setAttribute('url.path', $uri->getPath() ?: '/')
            ->startSpan();

        return new PreparedOutboundRequest($request, $span, $span->activate());
    }

    public static function succeed(
        PreparedOutboundRequest $prepared,
        ResponseInterface $response,
    ): ResponseInterface {
        $prepared->span->setAttribute('http.response.status_code', $response->getStatusCode());
        $prepared->close();

        return $response;
    }

    public static function fail(PreparedOutboundRequest $prepared, Throwable $reason): void
    {
        $prepared->span->recordException($reason);
        $prepared->span->setStatus(StatusCode::STATUS_ERROR, $reason->getMessage());
        $prepared->close();
    }

    private static function spanName(RequestInterface $request): string
    {
        $host = $request->getUri()->getHost();
        $path = $request->getUri()->getPath() ?: '/';

        // Host plus the first two path segments keeps outbound spans readable
        // without turning every distinct ID into its own time series.
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $prefix   = implode('/', array_slice($segments, 0, 2));

        return $prefix !== '' ? $host . '/' . $prefix : $host;
    }
}

final class PreparedOutboundRequest
{
    public function __construct(
        public RequestInterface $request,
        public SpanInterface $span,
        private readonly ScopeInterface $scope,
    ) {
    }

    public function close(): void
    {
        $this->scope->detach();
        $this->span->end();
    }
}
