<?php

// UpdateSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Data\Log;
use BlueFission\System\Machine;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class StatusSkill extends BaseSkill
{
    protected $response;
    protected ?Machine $machine;
    protected ?Log $log;
    protected ?string $logPath;

    public function __construct(?Machine $machine = null, ?Log $log = null, ?string $logPath = null)
    {
        parent::__construct('Update Skill');
        $this->machine = $machine;
        $this->log = $log;
        $this->logPath = $logPath;
    }

    public function execute(Context $context = null)
    {
        $machine = $this->machine ?? new Machine();
        $log = $this->log;
        $logPath = $this->logPath ?? (defined('OPUS_ROOT') ? OPUS_ROOT . 'storage/error.log' : null);
        if (!$log && Val::isNotEmpty($logPath)) {
            $log = Log::instance();
            $log->config(['file' => $logPath]);
        }
        $recentLogMessages = $this->getRecentLogMessages($log);
        $eventLogs = ''; // Retrieve recent event logs here
        $currentStatus = $machine->getOS() . ' - ' . $machine->getMemoryUsage() . ' bytes used - ' . $machine->getMemoryPeakUsage() . ' bytes peak used - ' . $machine->getUptime() . ' seconds uptime - ' . $machine->getCPUUsage() . ' CPU usage';

        $response = "Here is an update:\n\nRecent log messages:\n$recentLogMessages\n\nEvent logs:\n$eventLogs\n\nSystem details:\n$currentStatus";
        $this->response = $response;
    }

    private function getRecentLogMessages(?Log $log): string
    {
        if (!$log) {
            return 'No log source configured.';
        }

        $logData = $log->read();
        $logLines = Str::split((string)$logData, "\n");
        $recentLogMessages = Arr::slice($logLines, -10);

        $messages = '';
        foreach ($recentLogMessages as $message) {
            $messages = Val::isEmpty($messages)
                ? (string)$message
                : Str::make($messages)->append("\n")->append((string)$message)->val();
        }

        return $messages;
    }

    public function response(): string
    {
        return $this->response;
    }
}
