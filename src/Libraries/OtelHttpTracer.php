<?php

namespace Iqtool\CiOtel\Libraries;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

class OtelHttpTracer
{
    /**
     * Starts a span for an outgoing HTTP request and injects propagation headers.
     *
     * @param string $method  HTTP Method (GET, POST, etc.)
     * @param string $url     Target URL
     * @param array  $headers Reference to headers array to inject Trace Context
     */
    public function startSpan(string $method, string $url, array &$headers): SpanInterface
    {
        $tracer   = OtelProvider::getTracer();
        $spanName = sprintf('HTTP %s', strtoupper($method));

        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', strtoupper($method))
            ->setAttribute('http.url', $url)
            ->startSpan();

        // Inject Trace Context into headers
        $context    = $span->storeInContext(Context::getCurrent());
        $propagator = TraceContextPropagator::getInstance();
        $propagator->inject($headers, null, $context);

        return $span;
    }

    /**
     * Ends the span with status code and validation.
     *
     * @param SpanInterface $span       The active span
     * @param int           $statusCode HTTP Status Code from response
     */
    public function endSpan(SpanInterface $span, int $statusCode): void
    {
        $span->setAttribute('http.status_code', $statusCode);

        if ($statusCode >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
    }
}
