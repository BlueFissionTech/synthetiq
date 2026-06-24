<?php

use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\SynthetIQ\Clients\NullLocationClient;
use BlueFission\SynthetIQ\Clients\NullNewsClient;
use BlueFission\SynthetIQ\Clients\NullWeatherClient;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;
use BlueFission\SimpleClients\GeoLocationClient;
use BlueFission\SimpleClients\OpenWeatherClient;
use BlueFission\SimpleClients\WikiNewsClient;

return [
    'enabled' => [
        LocationClientInterface::class => true,
        WeatherClientInterface::class => false,
        NewsClientInterface::class => false,
    ],
    'bindings' => [
        LocationClientInterface::class => NullLocationClient::class,
        WeatherClientInterface::class => NullWeatherClient::class,
        NewsClientInterface::class => NullNewsClient::class,
    ],
    'available' => [
        LocationClientInterface::class => GeoLocationClient::class,
        WeatherClientInterface::class => OpenWeatherClient::class,
        NewsClientInterface::class => WikiNewsClient::class,
    ],
];
