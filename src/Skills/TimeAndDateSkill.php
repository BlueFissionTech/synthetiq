<?php
// TimeAndDateSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Utils\DateTime;
use BlueFission\Str;

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
        $message = Str::lower($context->get('message'));
        $responseParts = [];

        if (Str::has($message, 'time')) {
            $currentTime = $dateTimeUtil->time(time());
            $responseParts[] = "The current time is {$currentTime}";
        }

        if (Str::has($message, 'date')) {
            $currentDate = $dateTimeUtil->date(time());
            $responseParts[] = "The current date is {$currentDate}";
        }

        if (Str::has($message, 'zone')) {
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
