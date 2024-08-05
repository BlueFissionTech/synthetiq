<?php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;

class WeatherSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('Open Weather Skill');
    }

    public function execute(Context $context = null)
    {
        $location = $context->get('location');
        $weather = instance('openweather');
        $loc = instance('location');
        // Use the User's IP or connection to estimage a location if context is empty
        if (empty($location)) {
            $location = $loc->getIpLocation();
        }

        $this->response = $weather->getWeatherByLocation($location);
        return $this->response;
    }

    public function response(): string
    {
        return $this->response;
    }
}
