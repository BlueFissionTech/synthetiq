<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Clients;

use BlueFission\SynthetIQ\Clients\ClientResolver;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NullLocationClient;
use BlueFission\SynthetIQ\Clients\NullNewsClient;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;
use PHPUnit\Framework\TestCase;

final class ClientResolverTest extends TestCase
{
    public function testResolverHonorsExplicitDisabledClients(): void
    {
        $resolver = new ClientResolver([
            'enabled' => [
                WeatherClientInterface::class => false,
            ],
            'bindings' => [
                WeatherClientInterface::class => FakeWeatherClient::class,
            ],
        ]);

        $this->assertNull($resolver->client(WeatherClientInterface::class));
    }

    public function testResolverBuildsConfiguredClients(): void
    {
        $resolver = new ClientResolver([
            'bindings' => [
                LocationClientInterface::class => NullLocationClient::class,
                WeatherClientInterface::class => FakeWeatherClient::class,
                NewsClientInterface::class => static fn(): NewsClientInterface => new NullNewsClient(),
            ],
        ]);

        $this->assertInstanceOf(NullLocationClient::class, $resolver->client(LocationClientInterface::class));
        $this->assertInstanceOf(FakeWeatherClient::class, $resolver->client(WeatherClientInterface::class));
        $this->assertInstanceOf(NullNewsClient::class, $resolver->client(NewsClientInterface::class));
    }

    public function testSampleConfigKeepsExternalClientsDisabledByDefault(): void
    {
        $resolver = new ClientResolver(require dirname(__DIR__, 2) . '/sample_configs/clients.php');

        $this->assertInstanceOf(LocationClientInterface::class, $resolver->client(LocationClientInterface::class));
        $this->assertNull($resolver->client(WeatherClientInterface::class));
        $this->assertNull($resolver->client(NewsClientInterface::class));
    }
}

final class FakeWeatherClient implements WeatherClientInterface
{
    public function getWeatherByLocation(string $location): string
    {
        return "Weather for {$location}";
    }
}
