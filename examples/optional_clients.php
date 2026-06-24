<?php

declare(strict_types=1);

use BlueFission\Automata\Context;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;
use BlueFission\SynthetIQ\Skills\NewsSkill;
use BlueFission\SynthetIQ\Skills\WeatherSkill;

require dirname(__DIR__) . '/vendor/autoload.php';

final class ExampleLocationClient implements LocationClientInterface
{
    public function getIpLocation(): string
    {
        return 'Detroit';
    }
}

final class ExampleWeatherClient implements WeatherClientInterface
{
    public function getWeatherByLocation(string $location): string
    {
        return "Clear skies in {$location}.";
    }
}

final class ExampleNewsClient implements NewsClientInterface
{
    public function getHeadlines(string $topic = '', string $location = ''): array
    {
        return [
            "{$topic} briefing for {$location}",
            "{$location} service update",
        ];
    }
}

$context = new Context();
$context->set('topic', 'Technology');

$disabledWeather = new WeatherSkill();
$disabledWeather->execute($context);

$location = new ExampleLocationClient();
$enabledWeather = new WeatherSkill(new ExampleWeatherClient(), $location);
$enabledNews = new NewsSkill(new ExampleNewsClient(), $location);

$enabledWeather->execute($context);
$enabledNews->execute($context);

$payload = [
    'disabled' => [
        'weather' => $disabledWeather->response(),
    ],
    'enabled' => [
        'weather' => $enabledWeather->response(),
        'news' => $enabledNews->response(),
    ],
];

$json = HTTP::jsonEncode($payload);
echo (Str::is($json) ? $json : '{}') . PHP_EOL;
