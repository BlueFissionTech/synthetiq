<?php

use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;
use BlueFission\SimpleClients\GeoLocationClient;
use BlueFission\SimpleClients\OpenWeatherClient;
use BlueFission\SimpleClients\WikiNewsClient;

return [
    // LocationClientInterface: getIpLocation(): string
    LocationClientInterface::class => GeoLocationClient::class,
    // WeatherClientInterface: getWeatherByLocation(string $location): string
    WeatherClientInterface::class => OpenWeatherClient::class,
    // NewsClientInterface: getHeadlines(string $topic = '', string $location = ''): array
    NewsClientInterface::class => WikiNewsClient::class,
];
