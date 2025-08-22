<?php
declare(strict_types=1);

namespace Spatial\Telemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class OpenTelemetryMiddleware
{
    public function __construct(private TracerInterface $tracer, private LoggerInterface $logger)
    {}

    public function handle(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        // Start the span for the entire request
        $span = $this->tracer->spanBuilder('http.request')->startSpan();

        // Set standard HTTP attributes
        $this->setHttpRequestAttributes($span, $request);

        // Activate the span
        $scope = $span->activate();

        try {
            // Process the request
            $result = $next($request, $response);

            // Set response attributes
            $this->setHttpResponseAttributes($span, $response);

            return $result;
        } catch (Exception $e) {
            // Record the exception
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            // Set error status code
            $span->setAttribute('http.status_code', $response->getStatusCode());

            throw $e;
        } finally {
            // Always end the span
            $span->end();
            $scope->detach();
        }
    }

    private function setHttpRequestAttributes(SpanInterface $span, RequestInterface $request): void
    {
        $span->setAttribute('http.method', $request->getMethod());
        $span->setAttribute('http.target', $request->getUri());
        $span->setAttribute('http.route', $this->getRoutePattern($request));
        $span->setAttribute('http.url', $request->getUri());
        $span->setAttribute('http.user_agent', $request->getHeader('User-Agent'));
        $span->setAttribute('http.request_content_length', $request->getHeader('Content-Length'));
        $span->setAttribute('http.scheme', $request->getUri()->getScheme());

        $span->setAttribute('net.host.name', $request->getUri()->getHost());
        $span->setAttribute('net.host.port', $request->getUri()->getPort());
        $span->setAttribute('net.peer.ip', $this->getClientIp($request));

        // Add custom headers if needed (be careful with sensitive data)
        $span->setAttribute('http.request_id', $request->getHeader('X-Request-ID'));
    }

    private function setHttpResponseAttributes(SpanInterface $span, ResponseInterface $response): void
    {
        $span->setAttribute('http.status_code', $response->getStatusCode());
        $span->setAttribute('http.response_content_length', $response->getHeader('Content-Length'));

        // Set span status based on HTTP status code
        if ($response->getStatusCode() >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$response->getStatusCode()}");
        }
    }

    private function getRoutePattern(RequestInterface $request): string
    {
        // Implementation depends on your framework
        // For example, in Laravel:
        // return $request->route() ? $request->route()->uri() : '';

        // For Symfony:
        // return $request->attributes->get('_route', '');

        return $request->getUri()->get();
    }


    private function getClientIp(RequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for various headers that might contain the real client IP
        $ipHeaders = [
            'X-Forwarded-For',
            'CF-Connecting-IP', // Cloudflare
            'True-Client-IP',
            'X-Real-IP',
            'X-Cluster-Client-IP',
        ];

        foreach ($ipHeaders as $header) {
            if ($request->hasHeader($header)) {
                $ips = explode(',', $request->getHeaderLine($header));
                return trim($ips[0]);
            }
        }

        // Fall back to server remote address
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}