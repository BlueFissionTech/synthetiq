<?php
// GreetingResponseSkill.php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\Str;

class GreetingResponseSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('Greeting Response');
    }

    public function execute(Context $context = null)
    {
        $hour = date('H');
        $greeting = '';
        $name = null;
        $message = '';
        $correction = '';
        if ($context) {
            $name = $context->get('username');
            $message = Str::lower($context->get('message'));
        }

        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good morning';
            if (Str::pos($message, 'afternoon') !== false || Str::pos($message, 'evening') !== false) {
                $correction = ', actually';
            }
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good afternoon';
            if (Str::pos($message, 'morning') !== false || Str::pos($message, 'evening') !== false) {
                $correction = ', actually';
            }
        } elseif ($hour >= 18 || $hour < 5) {
            $greeting = 'Good evening';
            if (Str::pos($message, 'morning') !== false || Str::pos($message, 'afternoon') !== false) {
                $correction = ', actually';
            }
        }

        if ($name) {
            $this->response = "{$greeting}, {$name}!";
        } else {
            $this->response = "{$greeting}{$correction}.";
        }
    }

    public function response(): string
    {
        return $this->response;
    }
}
