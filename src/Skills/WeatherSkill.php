<?php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;

class WeatherSkill extends BaseSkill
{
    protected $response;
    protected $weather_client;
    protected $location_client;

    public function __construct(?WeatherClientInterface $weatherClient = null, ?LocationClientInterface $locationClient = null)
    {
        parent::__construct('Open Weather Skill');
        $this->weather_client = $weatherClient;
        $this->location_client = $locationClient;
    }

    public function execute(Context $context = null)
    {
        $location = $context->get('location');
        $weather = $this->weather_client;
        $loc = $this->location_client;

        if (!$weather) {
            $this->response = 'Weather service is not configured.';
            return;
        }

        // Use the User's IP or connection to estimage a location if context is empty
        if (empty($location)) {
            $location = $loc ? $loc->getIpLocation() : 'New York';
        }

        $this->response = $weather->getWeatherByLocation($location);
        return $this->response;
    }

    public function response(): string
    {
        return $this->response;
    }
}
