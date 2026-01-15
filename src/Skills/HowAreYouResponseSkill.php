<?php
// HowAreYouResponseSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\System\Machine;
use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Str;

class HowAreYouResponseSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('How Are You Response');
    }

    public function execute(Context $context = null)
    {
        $machine = new Machine();

        try {
            $memoryUsage = $machine->getMemoryUsage();
            $memoryPeakUsage = $machine->getMemoryPeakUsage();
            $uptime = $machine->getUptime();
            $cpuUsage = $machine->getCPUUsage();
            $temperature = $machine->getTemperature();
            $fanSpeed = $machine->getFanSpeed();
            $powerConsumption = $machine->getPowerConsumption();
        } catch (\Exception $e) {
            $this->response = "I'm not doing well, there was an error retrieving my system resources.";
            return;
        }

        $userMessage = Str::lower($context->get('message') ?? "");
        $feelingsKeywords = ['I\'m', 'I am', 'fine'];

        $userMentionedFeelings = false;
        foreach ($feelingsKeywords as $keyword) {
            if (Str::pos($userMessage, $keyword) !== false) {
                $userMentionedFeelings = true;
                break;
            }
        }

        if ($cpuUsage > 80 || $memoryUsage > 0.8 * $memoryPeakUsage) {
            $this->response = "I'm not doing well, my system resources are being heavily utilized.";
        } elseif (count($context->get('history')) > 100 || Arr::has($context->get('state'), 'ErrorState')) {
            $this->response = "I'm not doing well, there have been some errors and my history is quite long.";
        } else {
            $this->response = "I'm doing well, thank you! My system resources are in good condition and there are no major issues.";
        }

        if (!$userMentionedFeelings) {
            $howAreYouPhrases = ["How are you?", "And you?", "How are you doing?", "How are you today?", "What about you?"];
            $randomPhrase = (new Collection($howAreYouPhrases))->rand();
            $this->response .= " " . $randomPhrase;
        }
    }

    public function response(): string
    {
        return $this->response;
    }
}
