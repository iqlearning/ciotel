<?php

namespace Iqtool\CiOtel\Libraries;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporterFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactory;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class OtelProvider
{
    private static ?TracerProvider $tracerProvider = null;
    private static ?LoggerProvider $loggerProvider = null;
    private static ?TracerInterface $tracer        = null;

    public static function getTracerProvider(): TracerProvider
    {
        if (self::$tracerProvider === null) {
            $resource = ResourceInfoFactory::defaultResource();

            // Use Factory which reads OTEL_EXPORTER_OTLP_ENDPOINT etc. from env
            $spanExporter = (new SpanExporterFactory())->create();

            // Use SimpleSpanProcessor for immediate export (easier for manual setup without internal clock/async issues)
            $spanProcessor = new SimpleSpanProcessor($spanExporter);

            self::$tracerProvider = new TracerProvider(
                $spanProcessor,
                null,
                $resource,
            );
        }

        return self::$tracerProvider;
    }

    public static function getTracer(): TracerInterface
    {
        if (self::$tracer === null) {
            $provider = self::getTracerProvider();
            // Use service name as scope name or just 'ci-otel'
            self::$tracer = $provider->getTracer('ci-otel-instrumentation');
        }

        return self::$tracer;
    }

    public static function getLoggerProvider(): LoggerProvider
    {
        if (self::$loggerProvider === null) {
            $resource = ResourceInfoFactory::defaultResource();

            $logExporter = (new LogsExporterFactory())->create();

            $logProcessor = new SimpleLogRecordProcessor($logExporter);

            self::$loggerProvider = new LoggerProvider(
                $logProcessor,
                new InstrumentationScopeFactory(new AttributesFactory()),
                $resource,
            );
        }

        return self::$loggerProvider;
    }

    public static function shutdown(): void
    {
        if (self::$tracerProvider) {
            self::$tracerProvider->shutdown();
        }
        if (self::$loggerProvider) {
            self::$loggerProvider->shutdown();
        }
    }
}
