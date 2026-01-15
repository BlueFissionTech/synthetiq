<?php

// UpdateSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Data\Log;
use BlueFission\System\Machine;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Arr;
use BlueFission\Str;

class StatusSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('Update Skill');
    }

    public function execute(Context $context = null)
    {
        $machine = new Machine();
        $log = Log::instance();
        $log->config(['file'=>OPUS_ROOT.'storage/error.log']);
        $userMessage = Str::lower($context->get('message') ?? "");

        $recentLogMessages = $this->getRecentLogMessages($log);
        $eventLogs = ''; // Retrieve recent event logs here
        $currentStatus = $machine->getOS() . ' - ' . $machine->getMemoryUsage() . ' bytes used - ' . $machine->getMemoryPeakUsage() . ' bytes peak used - ' . $machine->getUptime() . ' seconds uptime - ' . $machine->getCPUUsage() . ' CPU usage';

        $response = "Here is an update:\n\nRecent log messages:\n$recentLogMessages\n\nEvent logs:\n$eventLogs\n\nSystem details:\n$currentStatus";
        $this->response = $response;
    }

    private function getRecentLogMessages($log)
    {
        $logData = $log->read();
        $logLines = Str::split((string)$logData, "\n");
        $recentLogMessages = Arr::slice($logLines, -10);
        return implode("\n", $recentLogMessages);
    }

    public function response(): string
    {
        return $this->response;
    }
}
