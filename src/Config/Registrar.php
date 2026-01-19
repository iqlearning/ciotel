<?php

namespace Iqtool\CiOtel\Config;

use CodeIgniter\Events\Events;
use Iqtool\CiOtel\Filters\OtelFilter;
use Iqtool\CiOtel\Handlers\OtelHandler;
use Iqtool\CiOtel\Libraries\OtelProvider;
use Iqtool\CiOtel\Listeners\OtelDbListener;

class Registrar
{
    public static function Events(): array
    {
        return [
            Events::on('post_system', static function () {
                OtelProvider::shutdown();
            }),
            Events::on('DBQuery', [OtelDbListener::class, 'collect']),
        ];
    }

    public static function Filters(): array
    {
        return [
            'aliases' => ['otel' => OtelFilter::class],
            'globals' => [
                'before' => ['otel'],
                'after'  => ['otel'],
            ],
        ];
    }

    public static function Logger(): array
    {
        return [
            'handlers' => [OtelHandler::class => [
                'handles' => ['critical', 'alert', 'emergency', 'debug', 'error', 'info', 'notice', 'warning'],
            ]],
        ];
    }
}
