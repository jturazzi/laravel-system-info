<?php

namespace Anefloire\SystemInfo\Http;

use Anefloire\SystemInfo\SystemInfo;
use Illuminate\Http\JsonResponse;

class SystemInfoController
{
    public function __invoke(SystemInfo $systemInfo): JsonResponse
    {
        $report = $systemInfo->collect();

        // 503 si l'application est hors service, pour les sondes de supervision.
        return response()
            ->json($report, $report['status'] === SystemInfo::STATUS_DOWN ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
