<?php

namespace Anefloire\SystemInfo\Console;

use Anefloire\SystemInfo\SystemInfo;
use Illuminate\Console\Command;

class SystemInfoCommand extends Command
{
    protected $signature = 'system-info';

    protected $description = 'Affiche le rapport exposé par la route /api/system-info';

    public function handle(SystemInfo $systemInfo): int
    {
        $report = $systemInfo->collect();

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $report['status'] === SystemInfo::STATUS_DOWN ? self::FAILURE : self::SUCCESS;
    }
}
