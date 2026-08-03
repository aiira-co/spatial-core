<?php

declare(strict_types=1);

namespace Spatial\Telemetry;

use Monolog\Level;
use Monolog\Logger;
use OpenSwoole\Timer;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use Psr\Log\LoggerInterface;
use Throwable;

class OtelProviderFactory
{
    /** Default periodic metric export interval, matching OTEL_METRIC_EXPORT_INTERVAL's default. */
    private const DEFAULT_METRIC_EXPORT_INTERVAL_MS = 60_000;

    static TracerInterface $tracer;
    static MeterInterface $meter;

    private static ?TracerProvider $tracerProvider = null;
    private static ?LoggerProvider $loggerProvider = null;
    private static ?MeterProviderInterface $meterProvider = null;
    private static ?LoggerInterface $logger = null;
    private static bool $initialized = false;
    private static ?int $flushTimerId = null;

    /**
     * Build and return a Monolog logger integrated with OpenTelemetry.
     *
     * Idempotent: repeated calls return the logger built by the first one.
     * Without this guard, resolving the middleware more than once rebuilt the
     * whole SDK, re-registered it globally and stacked another shutdown
     * function on every call.
     *
     * @param non-empty-string $serviceName
     * @param non-empty-string $serviceVersion
     * @param non-empty-string|null $endpoint If null, will use OTEL_EXPORTER_OTLP_ENDPOINT env var
     */
    public static function create(
        string $serviceName,
        string $serviceVersion,
        ?string $endpoint = null
    ): LoggerInterface {
        if (self::$initialized && self::$logger !== null) {
            return self::$logger;
        }

        self::$initialized = true;

        // Initialize with default no-op providers to prevent uninitialized property access
        if (!isset(self::$tracer)) {
            self::$tracer = Globals::tracerProvider()->getTracer('io.opentelemetry.contrib.php');
        }
        if (!isset(self::$meter)) {
            self::$meter = Globals::meterProvider()->getMeter('io.opentelemetry.contrib.php');
        }

        // Check if OpenTelemetry is available
        if (!self::isOpenTelemetryAvailable()) {
            return self::$logger = new Logger($serviceName); // Fallback to regular Monolog
        }

        // Use environment variable if endpoint is not provided
        $endpoint = $endpoint ?? getenv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if (empty($endpoint)) {
            return self::$logger = new Logger($serviceName); // Fallback if no endpoint
        }

        $endpoint = self::normalizeEndpoint($endpoint);

        // Check if collector is reachable
        if (!self::isCollectorAvailable($endpoint)) {
            error_log(sprintf(
                'OpenTelemetry: collector at %s is unreachable; tracing and metrics are disabled for this process.',
                $endpoint
            ));

            return self::$logger = new Logger($serviceName); // Fallback to regular Monolog
        }

        try {
            // --- Service identity (resource attributes)
            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(
                    Attributes::create([
                        ServiceAttributes::SERVICE_NAME => $serviceName,
                        ServiceAttributes::SERVICE_VERSION => $serviceVersion,
                        // Add deployment environment if available
                        'deployment.environment' => getenv('APP_ENV') ?: 'production',
                    ])
                )
            );

            $transportFactory = new OtlpHttpTransportFactory();

            // --- Exporters with error handling
            $spanTransport = $transportFactory->create($endpoint . '/v1/traces', 'application/x-protobuf');
            $logTransport = $transportFactory->create($endpoint . '/v1/logs', 'application/x-protobuf');
            $metricTransport = $transportFactory->create($endpoint . '/v1/metrics', 'application/x-protobuf');

            $spanExporter = new SpanExporter($spanTransport);
            $logExporter = new LogsExporter($logTransport);
            $metricExporter = new MetricExporter($metricTransport);

            // --- Create InstrumentationScopeFactory
            $attributesFactory = new AttributesFactory();
            $instrumentationScopeFactory = new InstrumentationScopeFactory(
                $attributesFactory,
                Clock::getDefault()
            );

            // --- Providers
            $sampler = self::createSampler();
            $tracerProvider = new TracerProvider(
                spanProcessors: [
                    new BatchSpanProcessor(
                        $spanExporter,
                        Clock::getDefault()
                    )
                ],
                sampler: $sampler,
                resource: $resource,
                instrumentationScopeFactory: $instrumentationScopeFactory
            );

            self::$tracer = $tracerProvider->getTracer('io.opentelemetry.contrib.php');

            $loggerProvider = new LoggerProvider(
                processor: new BatchLogRecordProcessor($logExporter, Clock::getDefault()),
                instrumentationScopeFactory: $instrumentationScopeFactory,
                resource: $resource
            );

            // Use the builder for meter provider
            $reader = new ExportingReader($metricExporter);
            $meterProvider = (new MeterProviderBuilder())
                ->setResource($resource)
                ->setClock(Clock::getDefault())
                ->addReader($reader)
                ->build();

            self::$meter = $meterProvider->getMeter('io.opentelemetry.contrib.php');

            self::$tracerProvider = $tracerProvider;
            self::$loggerProvider = $loggerProvider;
            self::$meterProvider = $meterProvider;

            // --- Register globally
            Sdk::builder()
                ->setTracerProvider($tracerProvider)
                ->setMeterProvider($meterProvider)
                ->setLoggerProvider($loggerProvider)
                ->setPropagator(TraceContextPropagator::getInstance())
                ->buildAndRegisterGlobal();

            // Last-resort flush. Under Swoole this only runs when the process
            // itself exits, so shutdown() must also be called from onWorkerStop
            // and the SIGTERM handler.
            register_shutdown_function(static function (): void {
                self::shutdown();
            });

            // --- Monolog Logger with OTEL handler
            $logger = new Logger($serviceName);
            $otelHandler = new Handler($loggerProvider, Level::Debug);
            $logger->pushHandler($otelHandler);

            return self::$logger = $logger;
        } catch (Throwable $e) {
            // Log the error and fall back to regular Monolog
            error_log('OpenTelemetry initialization failed: ' . $e->getMessage());

            return self::$logger = new Logger($serviceName);
        }
    }

    /**
     * Start the periodic metric export timer for the current worker.
     *
     * ExportingReader has no internal schedule: it only exports when collect(),
     * forceFlush() or shutdown() is called. Because Swoole workers are
     * long-lived, without this tick the HTTP metrics accumulate in memory and
     * reach the collector only when the process finally exits.
     *
     * Call from onWorkerStart, after the workers have been forked.
     */
    public static function registerFlushTimer(?int $intervalMs = null): void
    {
        if (self::$meterProvider === null || self::$flushTimerId !== null) {
            return;
        }

        if (!class_exists(Timer::class)) {
            return;
        }

        $intervalMs ??= (int)(getenv('OTEL_METRIC_EXPORT_INTERVAL')
            ?: self::DEFAULT_METRIC_EXPORT_INTERVAL_MS);

        if ($intervalMs < 1_000) {
            $intervalMs = 1_000;
        }

        self::$flushTimerId = Timer::tick($intervalMs, static function (): void {
            self::forceFlush();
        });
    }

    /**
     * Export whatever is currently buffered without tearing the SDK down.
     */
    public static function forceFlush(): void
    {
        try {
            self::$meterProvider?->forceFlush();
            self::$tracerProvider?->forceFlush();
            self::$loggerProvider?->forceFlush();
        } catch (Throwable $e) {
            error_log('OpenTelemetry flush error: ' . $e->getMessage());
        }
    }

    /**
     * Flush and shut down every provider.
     *
     * Call from onWorkerStop and from the SIGTERM handler; otherwise a rolling
     * deploy discards whatever the batch processors still hold.
     */
    public static function shutdown(): void
    {
        if (self::$flushTimerId !== null && class_exists(Timer::class)) {
            Timer::clear(self::$flushTimerId);
            self::$flushTimerId = null;
        }

        try {
            self::$tracerProvider?->shutdown();
            self::$loggerProvider?->shutdown();
            self::$meterProvider?->shutdown();
        } catch (Throwable $e) {
            error_log('OpenTelemetry shutdown error: ' . $e->getMessage());
        }

        self::$tracerProvider = null;
        self::$loggerProvider = null;
        self::$meterProvider = null;
    }

    /**
     * Honour OTEL_TRACES_SAMPLER / OTEL_TRACES_SAMPLER_ARG (OpenTelemetry SDK defaults).
     *
     * Falls back to parentbased_always_on when the env vars are unset or invalid,
     * matching the TracerProvider constructor default.
     */
    private static function createSampler(): \OpenTelemetry\SDK\Trace\SamplerInterface
    {
        try {
            return (new SamplerFactory())->create();
        } catch (Throwable $e) {
            error_log('OpenTelemetry: invalid sampler configuration (' . $e->getMessage() . '); using parentbased_always_on.');

            return new \OpenTelemetry\SDK\Trace\Sampler\ParentBased(
                new \OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler()
            );
        }
    }

    /**
     * Check if required OpenTelemetry classes are available
     */
    private static function isOpenTelemetryAvailable(): bool
    {
        return class_exists(LogsExporter::class) &&
            class_exists(SpanExporter::class) &&
            class_exists(MetricExporter::class) &&
            class_exists(Handler::class);
    }

    /**
     * Ensure the endpoint carries a scheme.
     *
     * A bare `host:4318` parses as host+port here but produces a schemeless
     * exporter URL that the HTTP transport cannot use.
     */
    private static function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = rtrim(trim($endpoint), '/');

        if (!str_contains($endpoint, '://')) {
            $endpoint = 'http://' . $endpoint;
        }

        return $endpoint;
    }

    private static function isCollectorAvailable(string $endpoint): bool
    {
        $urlParts = parse_url($endpoint);
        $host = $urlParts['host'] ?? 'collector';
        $port = $urlParts['port'] ?? 4318;

        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
