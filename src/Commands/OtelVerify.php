<?php

namespace Iqtool\CiOtel\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Iqtool\CiOtel\Libraries\OtelHttpTracer;
use Iqtool\CiOtel\Libraries\OtelProvider;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

class OtelVerify extends BaseCommand
{
    protected $group       = 'Otel';
    protected $name        = 'otel:verify';
    protected $description = 'Verifies OpenTelemetry configuration by sending a test trace and log.';

    public function run(array $params)
    {
        CLI::write('Starting Otel Verification...', 'yellow');

        // 1. Test Tracing
        CLI::write('1. Testing Tracing...', 'white');

        try {
            $tracer = OtelProvider::getTracer();
            $span   = $tracer->spanBuilder('otel-verify-command')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setAttribute('command.name', 'otel:verify')
                ->startSpan();

            $span->addEvent('verification_event', ['attr' => 'value']);
            sleep(1); // Simulate work
            $span->end();
            CLI::write('   Trace sent successfully.', 'green');
        } catch (Throwable $e) {
            CLI::error('   Tracing failed: ' . $e->getMessage());
        }

        // 2. Test Logging
        CLI::write('2. Testing Logging...', 'white');

        try {
            // This uses CI4's logger which uses our OtelHandler
            log_message('error', 'Otel Verify Test Log Message');
            CLI::write('   Log message sent to CI4 logger.', 'green');
        } catch (Throwable $e) {
            CLI::error('   Logging failed: ' . $e->getMessage());
        }

        // 3. Test DB Tracing
        CLI::write('3. Testing DB Tracing...', 'white');

        try {
            // Create a temporary table and run a query
            $db = Database::connect('tests');
            $db->query('SELECT 1');
            CLI::write('   DB Query executed.', 'green');
        } catch (Throwable $e) {
            CLI::error('   DB Query failed (Check DB config?): ' . $e->getMessage());
        }

        // 4. Test HTTP Client (Mock)
        CLI::write('4. Testing Outgoing HTTP Tracing...', 'white');

        try {
            $httpTracer = new OtelHttpTracer();
            $headers    = ['User-Agent' => 'CI4-Otel-Verify'];
            $url        = 'https://example.com/api/test';

            $span = $httpTracer->startSpan('GET', $url, $headers);
            CLI::write('   Span started. Headers injected: ' . json_encode(array_keys($headers)), 'yellow');

            // Simulate delay
            usleep(100000);

            $httpTracer->endSpan($span, 200);
            CLI::write('   Span ended.', 'green');
        } catch (Throwable $e) {
            CLI::error('   HTTP Tracing failed: ' . $e->getMessage());
        }

        // 5. Force Flush
        CLI::write('5. Flushing providers...', 'white');

        try {
            OtelProvider::shutdown();
            CLI::write('   Shutdown/Flush complete.', 'green');
        } catch (Throwable $e) {
            CLI::error('   Flush failed: ' . $e->getMessage());
        }

        CLI::write('Verification complete. Check SigNoz at http://localhost:3301 (or your port).', 'cyan');
    }
}
