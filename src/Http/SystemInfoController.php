<?php

namespace Jturazzi\SystemInfo\Http;

use Illuminate\Http\JsonResponse;
use Jturazzi\SystemInfo\SystemInfo;

class SystemInfoController
{
    public function __invoke(SystemInfo $systemInfo): JsonResponse
    {
        $report = $systemInfo->collect();

        // 503 when the application is down, for monitoring probes.
        return response()
            ->json($report, $report['status'] === SystemInfo::STATUS_DOWN ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
