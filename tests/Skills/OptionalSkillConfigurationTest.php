<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Skills;

use BlueFission\Automata\Context;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;
use BlueFission\SynthetIQ\Skills\NewsSkill;
use BlueFission\SynthetIQ\Skills\StatusSkill;
use BlueFission\SynthetIQ\Skills\WeatherSkill;
use PHPUnit\Framework\TestCase;

final class OptionalSkillConfigurationTest extends TestCase
{
    public function testWeatherSkillReturnsConfiguredMessageWithoutClient(): void
    {
        $skill = new WeatherSkill();

        $this->assertNull($skill->execute(new Context()));
        $this->assertSame('Weather service is not configured.', $skill->response());
    }

    public function testWeatherSkillUsesInjectedClients(): void
    {
        $context = new Context();
        $skill = new WeatherSkill(new FakeWeatherClient(), new FakeLocationClient('Detroit'));

        $this->assertSame('Weather for Detroit', $skill->execute($context));
        $this->assertSame('Weather for Detroit', $skill->response());
    }

    public function testNewsSkillHandlesDisabledClientAndInjectedClient(): void
    {
        $disabled = new NewsSkill();
        $this->assertSame([], $disabled->execute(new Context()));
        $this->assertSame('No news found.', $disabled->response());

        $context = new Context();
        $context->set('topic', 'Technology');
        $enabled = new NewsSkill(new FakeNewsClient(), new FakeLocationClient('Detroit'));

        $this->assertSame(['Technology in Detroit'], $enabled->execute($context));
        $this->assertSame("Here are the latest news headlines:\n\nTechnology in Detroit", $enabled->response());
    }

    public function testStatusSkillDoesNotRequireOpusRoot(): void
    {
        $skill = new StatusSkill();
        $skill->execute(new Context());

        $this->assertStringContainsString('No log source configured.', $skill->response());
        $this->assertStringContainsString('System details:', $skill->response());
    }
}

final class FakeLocationClient implements LocationClientInterface
{
    public function __construct(private string $location)
    {
    }

    public function getIpLocation(): string
    {
        return $this->location;
    }
}

final class FakeNewsClient implements NewsClientInterface
{
    public function getHeadlines(string $topic = '', string $location = ''): array
    {
        return ["{$topic} in {$location}"];
    }
}

final class FakeWeatherClient implements WeatherClientInterface
{
    public function getWeatherByLocation(string $location): string
    {
        return "Weather for {$location}";
    }
}
