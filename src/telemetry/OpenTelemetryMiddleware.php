<?php
declare(strict_types=1);

namespace Spatial\Telemetry;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spatial\Core\App;

class OpenTelemetryMiddleware implements MiddlewareInterface
{
    private CounterInterface $requestCounter;
    private HistogramInterface $requestDuration;
    private TracerInterface $tracer;
    private LoggerInterface $logger;
    private MeterInterface $meter;

    public function __construct()
    {

        $this->_configureOtel();
        $this->_configMetrics();
    }

    private function _configureOtel(): void
    {

        // Initialize OpenTelemetry
        $this->logger = OtelProviderFactory::create(
            getenv('APP_NAME') ?: 'spatial-service',
            getenv('APP_VERSION') ?: '1.0.0',
            getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://collector:4318'
        );
        $this->tracer = OtelProviderFactory::$tracer;
        $this->meter = OtelProviderFactory::$meter;

//        register on DI
        App::$diContainer->set(LoggerInterface::class, $this->logger);
        App::$diContainer->set(TracerInterface::class, OtelProviderFactory::$tracer);
        App::$diContainer->set(MeterInterface::class, OtelProviderFactory::$meter);

    }

    private function _configMetrics(): void
    {
        // Counter for requests
        $this->requestCounter = $this->meter
            ->createCounter(
                name: 'http.server.request.count',
                unit: 'requests',
                description: 'Number of incoming HTTP requests'
            );

        // Histogram for request duration
        $this->requestDuration = $this->meter
            ->createHistogram(
                name: 'http.server.duration',
                unit: 'milliseconds',
                description: 'Duration of incoming HTTP requests');
    }


    /**
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler):ResponseInterface
    {
        // Start the span for the entire request
        $span = $this->tracer->spanBuilder('http.request')->startSpan();

        // Set standard HTTP attributes
        $this->setHttpRequestAttributes($span, $request);

        // Activate the span
        $scope = $span->activate();

        $startTime = hrtime(true); // high-res timer

        try {
            // Process the request
            $response = $handler->handle($request);

            // Set response attributes
            $this->setHttpResponseAttributes($span, $response);

            // ✅ Metric: increment request count
            $this->requestCounter->add(1, [
                'http.method' => $request->getMethod(),
                'http.route' => $request->getUri()->getPath(),
                'http.status_code' => $response->getStatusCode(),
            ]);

            return $response;
        } catch (\Exception $e) {
            // Record the exception
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            $this->requestCounter->add(1, [
                'http.method' => $request->getMethod(),
                'http.route' => $request->getUri()->getPath(),
                'http.status_code' => 500,
            ]);

            // Set error status code
//            $span->setAttribute('http.status_code', $response->getStatusCode());

            throw $e;
        } finally {

            $durationMs = (hrtime(true) - $startTime) / 1_000_000;

            // ✅ Metric: record request duration
            $this->requestDuration->record($durationMs, [
                'http.method' => $request->getMethod(),
                'http.route' => $request->getUri()->getPath(),
            ]);

            // Always end the span
            $span->end();
            $scope->detach();
        }
    }


    private function setHttpRequestAttributes(SpanInterface $span, RequestInterface $request): void
    {
        $span->setAttribute('http.method', $request->getMethod());
        $span->setAttribute('http.target', $request->getUri()->getQuery());
        $span->setAttribute('http.route', $this->getRoutePattern($request));
        $span->setAttribute('http.url', $request->getUri()->getQuery());
        $span->setAttribute('http.user_agent', $request->getHeaderLine('User-Agent'));
        $span->setAttribute('http.request_content_length', $request->getHeaderLine('Content-Length'));
        $span->setAttribute('http.scheme', $request->getUri()->getScheme());

        $span->setAttribute('net.host.name', $request->getUri()->getHost());
        $span->setAttribute('net.host.port', $request->getUri()->getPort());
        $span->setAttribute('net.peer.ip', $this->getClientIp($request));

        // Add custom headers if needed (be careful with sensitive data)
        $span->setAttribute('http.request_id', $request->getHeaderLine('X-Request-ID'));
    }

    private function setHttpResponseAttributes(SpanInterface $span, ResponseInterface $response): void
    {
        $span->setAttribute('http.status_code', $response->getStatusCode());
        $span->setAttribute('http.response_content_length', $response->getHeaderLine('Content-Length'));

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

        return $request->getUri()->getPath();
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