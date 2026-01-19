<?php

namespace Iqtool\CiOtel\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Iqtool\CiOtel\Libraries\OtelProvider;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

class OtelFilter implements FilterInterface
{
    private ?ScopeInterface $scope = null;
    private ?SpanInterface $span   = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            $tracer = OtelProvider::getTracer();
            $method = strtoupper($request->getMethod());
            $path   = $request->getUri()->getPath();

            $this->span = $tracer->spanBuilder(sprintf('%s %s', $method, $path))
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setAttribute('http.method', $method)
                ->setAttribute('http.url', (string) $request->getUri())
                ->setAttribute('http.route', $path)
                ->startSpan();

            $this->scope = $this->span->activate();
        } catch (Throwable $e) {
            log_message('error', 'OtelFilter before error: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            if ($this->span) {
                $statusCode = $response->getStatusCode();
                $this->span->setAttribute('http.status_code', $statusCode);

                if ($statusCode >= 500) {
                    $this->span->setStatus(StatusCode::STATUS_ERROR);
                }

                $this->span->end();
            }

            if ($this->scope) {
                $this->scope->detach();
            }
        } catch (Throwable $e) {
            log_message('error', 'OtelFilter after error: ' . $e->getMessage());
        }
    }
}
