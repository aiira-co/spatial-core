<?php

declare(strict_types=1);

namespace Spatial\Telemetry;

use Monolog\Level;
use Monolog\Logger;
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
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use Psr\Log\LoggerInterface;
use Throwable;

class OtelProviderFactory
{
    static TracerInterface $tracer;
    static MeterInterface $meter;
    /**
     * Build and return a Monolog logger integrated with OpenTelemetry.
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
        // Initialize with default no-op providers to prevent uninitialized property access
        if (!isset(self::$tracer)) {
            self::$tracer = Globals::tracerProvider()->getTracer('io.opentelemetry.contrib.php');
        }
        if (!isset(self::$meter)) {
            self::$meter = Globals::meterProvider()->getMeter('io.opentelemetry.contrib.php');
        }

        // Check if OpenTelemetry is available
        if (!self::isOpenTelemetryAvailable()) {
            return new Logger($serviceName); // Fallback to regular Monolog
        }

        // Use environment variable if endpoint is not provided
        $endpoint = $endpoint ?? getenv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if (empty($endpoint)) {
            return new Logger($serviceName); // Fallback if no endpoint
        }

        // Check if collector is reachable
        if (!self::isCollectorAvailable($endpoint)) {
            return new Logger($serviceName); // Fallback to regular Monolog
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
            $tracerProvider = new TracerProvider(
                spanProcessors:  [
                    new BatchSpanProcessor(
                        $spanExporter,
                        Clock::getDefault()
                    )
                ],
                resource:  $resource,
                instrumentationScopeFactory:  $instrumentationScopeFactory
            );

            self::$tracer =  $tracerProvider->getTracer('io.opentelemetry.contrib.php');

            $loggerProvider = new LoggerProvider(
                processor:  new BatchLogRecordProcessor($logExporter, Clock::getDefault()),
                instrumentationScopeFactory:  $instrumentationScopeFactory,
                resource:  $resource
            );

            // Use the builder for meter provider
            $reader = new ExportingReader($metricExporter);
            $meterProvider = (new MeterProviderBuilder())
                ->setResource($resource)
                ->setClock(Clock::getDefault())
                ->addReader($reader)
                ->build();

            self::$meter =  $meterProvider->getMeter('io.opentelemetry.contrib.php');

            // --- Register globally
            Sdk::builder()
                ->setTracerProvider($tracerProvider)
                ->setMeterProvider($meterProvider)
                ->setLoggerProvider($loggerProvider)
                ->buildAndRegisterGlobal();

            // Ensure shutdown flushes all telemetry
            register_shutdown_function(static function () use ($tracerProvider, $loggerProvider, $meterProvider): void {
                try {
                    $tracerProvider->shutdown();
                    $loggerProvider->shutdown();
                    $meterProvider->shutdown();
                } catch (Throwable $e) {
                    // Log shutdown errors if needed
                    error_log('OpenTelemetry shutdown error: ' . $e->getMessage());
                }
            });

            // --- Monolog Logger with OTEL handler
            $logger = new Logger($serviceName);
            $otelHandler = new Handler($loggerProvider, Level::Debug);
            $logger->pushHandler($otelHandler);

            return $logger;
        } catch (Throwable $e) {
            // Log the error and fall back to regular Monolog
            error_log('OpenTelemetry initialization failed: ' . $e->getMessage());
            return new Logger($serviceName);
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