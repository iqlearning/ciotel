<?php

namespace Iqtool\CiOtel\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Iqtool\CiOtel\Libraries\OtelProvider;
use OpenTelemetry\API\Logs\LogRecord;

class OtelImportLogs extends BaseCommand
{
    protected $group       = 'Otel';
    protected $name        = 'otel:import-logs';
    protected $description = 'Imports a log file to SigNoz. Usage: otel:import-logs [YYYY-MM-DD]';
    protected $usage       = 'otel:import-logs [date]';
    protected $arguments   = [
        'date' => 'The date of the log file to import (YYYY-MM-DD). Defaults to today.',
    ];

    public function run(array $params)
    {
        $date     = array_shift($params) ?? date('Y-m-d');
        $filePath = WRITEPATH . 'logs/log-' . $date . '.log';

        if (! is_file($filePath)) {
            CLI::error('Log file not found: ' . $filePath);

            return;
        }

        CLI::write('Importing logs from: ' . $filePath, 'yellow');

        $content = file_get_contents($filePath);
        $lines   = explode("\n", $content);
        $count   = 0;

        $loggerProvider = OtelProvider::getLoggerProvider();
        $logger         = $loggerProvider->getLogger('ci-otel-importer');

        // Regex to match start of log line: LEVEL - YYYY-MM-DD HH:MM:SS --> Message
        $pattern = '/^([A-Z]+)\s+-\s+(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\s+-->\s+(.*)$/';

        $currentEntry = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (empty($line)) {
                continue;
            }

            if (preg_match($pattern, $line, $matches)) {
                // If we have a previous entry pending, emit it now
                if ($currentEntry) {
                    $this->emitLog($logger, $currentEntry);
                    $count++;
                }

                // Start new entry
                $currentEntry = [
                    'level'   => $matches[1],
                    'time'    => $matches[2],
                    'message' => $matches[3],
                ];
            } else {
                // Append to current entry (stack trace or continued message)
                if ($currentEntry) {
                    $currentEntry['message'] .= "\n" . $line;
                }
            }
        }

        // Emit the last entry
        if ($currentEntry) {
            $this->emitLog($logger, $currentEntry);
            $count++;
        }

        OtelProvider::shutdown();
        CLI::write("Imported {$count} log entries.", 'green');
    }

    private function emitLog($logger, array $entry)
    {
        $timestamp     = strtotime($entry['time']);
        $nanoTimestamp = (int) ($timestamp * 1_000_000_000);

        $severityNumber = $this->getSeverityNumber($entry['level']);

        $record = (new LogRecord($entry['message']))
            ->setTimestamp($nanoTimestamp)
            ->setSeverityText($entry['level'])
            ->setSeverityNumber($severityNumber)
            ->setAttribute('log.imported', true)
            ->setAttribute('codeigniter.level', $entry['level']);

        $logger->emit($record);
    }

    private function getSeverityNumber(string $level): int
    {
        return match (strtoupper($level)) {
            'EMERGENCY' => 21,
            'ALERT'     => 20,
            'CRITICAL'  => 18,
            'error'     => 17, // Case insensitive match handling in logic but good to match exactly if possible
            'ERROR'     => 17,
            'WARNING'   => 13,
            'NOTICE'    => 10,
            'INFO'      => 9,
            'DEBUG'     => 5,
            default     => 9,
        };
    }
}
