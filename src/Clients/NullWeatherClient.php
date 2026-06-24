<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Clients;

class NullWeatherClient implements WeatherClientInterface
{
    public function getWeatherByLocation(string $location): string
    {
        return 'Weather service is not configured.';
    }
}
