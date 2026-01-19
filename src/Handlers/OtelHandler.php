<?php

namespace Iqtool\CiOtel\Handlers;

use CodeIgniter\Log\Handlers\BaseHandler;
use CodeIgniter\Log\Handlers\HandlerInterface;
use Iqtool\CiOtel\Libraries\OtelProvider;
use OpenTelemetry\API\Logs\LogRecord;
use Throwable;

class OtelHandler extends BaseHandler implements HandlerInterface
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    public function handle($level, $message): bool
    {
        try {
            $provider = OtelProvider::getLoggerProvider();
            $logger   = $provider->getLogger('ci-otel-logger');

            $severityNumber = $this->getSeverityNumber($level);

            $record = (new LogRecord((string) $message))
                ->setSeverityText(strtoupper($level))
                ->setSeverityNumber($severityNumber)
                ->setAttribute('codeigniter.level', $level)
                ->setTimestamp((int) (microtime(true) * 1_000_000_000));

            $logger->emit($record);
        } catch (Throwable $e) {
            // Prevent logging errors from crashing the app
        }

        return true;
    }

    private function getSeverityNumber(string $level): int
    {
        return match (strtolower($level)) {
            'emergency' => 21,
            'alert'     => 20,
            'critical'  => 18,
            'error'     => 17,
            'warning'   => 13,
            'notice'    => 10,
            'info'      => 9,
            'debug'     => 5,
            default     => 9,
        };
    }
}
