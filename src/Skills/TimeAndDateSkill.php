<?php
// TimeAndDateSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Utils\DateTime;

class TimeAndDateSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('Time and Date');
    }

    public function execute(Context $context = null)
    {
        $dateTimeUtil = new DateTime();
        $message = strtolower($context->get('message'));
        $responseParts = [];

        if (strpos($message, 'time') !== false) {
            $currentTime = $dateTimeUtil->time(time());
            $responseParts[] = "The current time is {$currentTime}";
        }

        if (strpos($message, 'date') !== false) {
            $currentDate = $dateTimeUtil->date(time());
            $responseParts[] = "The current date is {$currentDate}";
        }

        if (strpos($message, 'zone') !== false) {
            $timeZone = $dateTimeUtil->config('timezone');
            $responseParts[] = "The time zone is {$timeZone}";
        }

        if (empty($responseParts)) {
            $currentTime = $dateTimeUtil->time(time());
            $responseParts[] = "The current time is {$currentTime}";
        }

        $this->response = implode(', ', $responseParts) . '.';
    }

    public function response(): string
    {
        return $this->response;
    }
}
