<?php

namespace Iqtool\CiOtel\Listeners;

use CodeIgniter\Database\Query;
use Iqtool\CiOtel\Libraries\OtelProvider;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

class OtelDbListener
{
    /**
     * @param Query $query
     */
    public static function collect($query)
    {
        try {
            $tracer = OtelProvider::getTracer();
            $sql    = $query->getQuery();

            // Calculate timestamps in nanoseconds
            // getStartTime(true) gives float seconds. * 1B -> nanoseconds.
            $startTime = (float) $query->getStartTime(true);
            $duration  = (float) $query->getDuration(10); // High precision

            // Ensure logical time progression
            $startTimestamp = (int) ($startTime * 1_000_000_000);
            $endTimestamp   = (int) (($startTime + $duration) * 1_000_000_000);

            $span = $tracer->spanBuilder('db.query')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setStartTimestamp($startTimestamp)
                ->setAttribute('db.system', env('database.default.DBDriver', 'sqlite')) // Defaulting to generic or mysql
                ->setAttribute('db.statement', $sql)
                ->setAttribute('db.name', env('database.default.database', ''))
                ->setAttribute('db.hostname', env('database.default.hostname', 'localhost'))
                ->startSpan();

            if ($query->hasError()) {
                $span->setAttribute('error', true);
                $span->setAttribute('db.error_message', $query->getErrorMessage());
                $span->setStatus(StatusCode::STATUS_ERROR, $query->getErrorMessage());
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            $span->end($endTimestamp);
        } catch (Throwable $e) {
            // Squelch errors to avoid breaking app execution
            // We cannot log here easily if the logger itself is what we are tracing or if it causes loops
        }
    }
}
