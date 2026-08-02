<?php
declare(strict_types=1);

namespace Spatial\Telemetry;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spatial\Core\App;

class OpenTelemetryMiddleware implements MiddlewareInterface
{
    /**
     * Request attributes a router may set to supply the matched route
     * template. Checked in order; the path is normalized only as a fallback.
     */
    private const ROUTE_ATTRIBUTES = ['spatial.route', '_route', 'route'];

    /** Fallback route label. Bounded by design — never a raw URL. */
    private const UNKNOWN_ROUTE = 'unmatched';

    private CounterInterface $requestCounter;
    private HistogramInterface $requestDuration;
    private TracerInterface $tracer;
    private LoggerInterface $logger;
    private MeterInterface $meter;

    /** Emit the stable HTTP semantic conventions (http.request.method, ...). */
    private bool $emitStable = true;

    /** Also emit the pre-1.0 names (http.method, ...) during dashboard migration. */
    private bool $emitLegacy = false;

    public function __construct()
    {

        $this->_configureOtel();
        $this->_configMetrics();
        $this->_configSemconv();
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
     * Honour the standard opt-in used to migrate HTTP attribute names.
     *
     * unset      emit the stable names only
     * http       emit the pre-1.0 names only
     * http/dup   emit both, so dashboards can be cut over without a gap
     */
    private function _configSemconv(): void
    {
        $optIn = strtolower(trim((string)getenv('OTEL_SEMCONV_STABILITY_OPT_IN')));

        $this->emitLegacy = $optIn === 'http' || $optIn === 'http/dup';
        $this->emitStable = $optIn !== 'http';
    }


    /**
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler):ResponseInterface
    {
        $method = $request->getMethod();
        $route = $this->resolveRoute($request);

        // Span name follows the stable convention "{method} {route}", which is
        // readable in a trace list and stays low-cardinality.
        $span = $this->tracer
            ->spanBuilder($method . ' ' . $route)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        // Set standard HTTP attributes
        $this->setHttpRequestAttributes($span, $request, $route);

        // Activate the span
        $scope = $span->activate();

        $startTime = hrtime(true); // high-res timer

        $statusCode = 500;

        try {
            // Process the request
            $response = $handler->handle($request);
            $statusCode = $response->getStatusCode();

            // Set response attributes
            $this->setHttpResponseAttributes($span, $response);

            return $response;
        } catch (\Throwable $e) {
            // Record the exception
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $durationMs = (hrtime(true) - $startTime) / 1_000_000;

            // Metric attributes are deliberately limited to this bounded set.
            // `route` is a template, never a raw path: using the path made every
            // distinct URL its own time series.
            $metricAttributes = $this->metricAttributes($method, $route, $statusCode);

            $this->requestCounter->add(1, $metricAttributes);
            $this->requestDuration->record($durationMs, $metricAttributes);

            // Detach the scope before ending the span: the scope must be
            // unwound while the span it activated is still current.
            $scope->detach();
            $span->end();
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function metricAttributes(string $method, string $route, int $statusCode): array
    {
        $attributes = [];

        if ($this->emitStable) {
            $attributes['http.request.method'] = $method;
            $attributes['http.route'] = $route;
            $attributes['http.response.status_code'] = $statusCode;
        }

        if ($this->emitLegacy) {
            $attributes['http.method'] = $method;
            $attributes['http.status_code'] = $statusCode;

            if (!$this->emitStable) {
                $attributes['http.route'] = $route;
            }
        }

        return $attributes;
    }

    private function setHttpRequestAttributes(
        SpanInterface $span,
        ServerRequestInterface $request,
        string $route
    ): void {
        $uri = $request->getUri();
        $method = $request->getMethod();
        $clientIp = $this->getClientIp($request);

        if ($this->emitStable) {
            $span->setAttribute('http.request.method', $method);
            $span->setAttribute('http.route', $route);
            // Full URL, not the query string — the previous code assigned
            // getQuery() to both http.url and http.target.
            $span->setAttribute('url.full', (string)$uri);
            $span->setAttribute('url.path', $uri->getPath());
            $span->setAttribute('url.scheme', $uri->getScheme());
            $span->setAttribute('user_agent.original', $request->getHeaderLine('User-Agent'));
            $span->setAttribute('server.address', $uri->getHost());
            $span->setAttribute('server.port', $uri->getPort());
            $span->setAttribute('client.address', $clientIp);

            if ($query = $uri->getQuery()) {
                $span->setAttribute('url.query', $query);
            }
        }

        if ($this->emitLegacy) {
            $span->setAttribute('http.method', $method);
            $span->setAttribute('http.route', $route);
            $span->setAttribute('http.target', $uri->getPath());
            $span->setAttribute('http.url', (string)$uri);
            $span->setAttribute('http.user_agent', $request->getHeaderLine('User-Agent'));
            $span->setAttribute('http.scheme', $uri->getScheme());
            $span->setAttribute('net.host.name', $uri->getHost());
            $span->setAttribute('net.host.port', $uri->getPort());
            $span->setAttribute('net.peer.ip', $clientIp);
        }

        if ($contentLength = $request->getHeaderLine('Content-Length')) {
            $span->setAttribute('http.request.body.size', (int)$contentLength);
        }

        if ($requestId = $request->getHeaderLine('X-Request-ID')) {
            $span->setAttribute('http.request_id', $requestId);
        }
    }

    private function setHttpResponseAttributes(SpanInterface $span, ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($this->emitStable) {
            $span->setAttribute('http.response.status_code', $statusCode);
        }

        if ($this->emitLegacy) {
            $span->setAttribute('http.status_code', $statusCode);
        }

        if ($contentLength = $response->getHeaderLine('Content-Length')) {
            $span->setAttribute('http.response.body.size', (int)$contentLength);
        }

        // Set span status based on HTTP status code
        if ($statusCode >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
        }
    }

    /**
     * The route template for this request.
     *
     * Prefers a template published by the router; otherwise infers one by
     * replacing identifier-shaped path segments with placeholders. Either way
     * the result is bounded by the number of routes, not by the number of
     * URLs ever requested.
     */
    private function resolveRoute(ServerRequestInterface $request): string
    {
        foreach (self::ROUTE_ATTRIBUTES as $attribute) {
            $value = $request->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->normalizePath($request->getUri()->getPath());
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return '/';
        }

        $segments = explode('/', ltrim($path, '/'));

        foreach ($segments as $index => $segment) {
            $placeholder = $this->placeholderFor($segment);

            if ($placeholder !== null) {
                $segments[$index] = $placeholder;
            }
        }

        return '/' . implode('/', $segments);
    }

    /**
     * A placeholder when the segment looks like an identifier, else null.
     */
    private function placeholderFor(string $segment): ?string
    {
        if ($segment === '') {
            return null;
        }

        if (ctype_digit($segment)) {
            return '{id}';
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment)) {
            return '{uuid}';
        }

        // Crockford base32, as used by ULIDs.
        if (preg_match('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{26}$/i', $segment)) {
            return '{ulid}';
        }

        if (preg_match('/^[0-9a-f]{24,}$/i', $segment)) {
            return '{hash}';
        }

        // Long mixed-alphanumeric segments are slugs or opaque tokens rather
        // than route literals; route words of this length do not carry digits.
        if (strlen($segment) >= 12 && preg_match('/\d/', $segment)) {
            return '{id}';
        }

        return null;
    }


    private function getClientIp(ServerRequestInterface $request): string
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
