<?php

// UpdateSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Data\Log;
use BlueFission\System\Machine;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;

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
        $userMessage = strtolower($context->get('message') ?? "");

        $recentLogMessages = $this->getRecentLogMessages($log);
        $eventLogs = ''; // Retrieve recent event logs here
        $currentStatus = $machine->getOS() . ' - ' . $machine->getMemoryUsage() . ' bytes used - ' . $machine->getMemoryPeakUsage() . ' bytes peak used - ' . $machine->getUptime() . ' seconds uptime - ' . $machine->getCPUUsage() . ' CPU usage';

        $response = "Here is an update:\n\nRecent log messages:\n$recentLogMessages\n\nEvent logs:\n$eventLogs\n\nSystem details:\n$currentStatus";
        $this->response = $response;
    }

    private function getRecentLogMessages($log)
    {
        $logData = $log->read();
        $recentLogMessages = implode("\n", array_slice(explode("\n", $logData), -10));
        return $recentLogMessages;
    }

    public function response(): string
    {
        return $this->response;
    }
}
