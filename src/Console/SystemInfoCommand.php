<?php

namespace Jturazzi\SystemInfo\Console;

use Illuminate\Console\Command;
use Jturazzi\SystemInfo\SystemInfo;

class SystemInfoCommand extends Command
{
    protected $signature = 'system-info';

    protected $description = 'Display the report exposed by the /api/system-info route';

    public function handle(SystemInfo $systemInfo): int
    {
        $report = $systemInfo->collect();

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $report['status'] === SystemInfo::STATUS_DOWN ? self::FAILURE : self::SUCCESS;
    }
}
