<?php

namespace App\V5\Logging;

use Illuminate\Http\Request;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $request = request();

        if ($request instanceof Request) {
            $extra = $record->extra ?? [];
            $extra = array_merge($extra, [
                'correlation_id' => V5Logger::getCorrelationId(),
                'user_id' => $request->user()?->id,
                'season_id' => $request->get('season_id'),
                'school_id' => $request->get('school_id'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            $record->extra = $extra;
        }

        return $record;
    }
}
