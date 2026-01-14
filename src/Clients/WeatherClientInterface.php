<?php

namespace BlueFission\SynthetIQ\Clients;

interface WeatherClientInterface
{
    public function getWeatherByLocation(string $location): string;
}
