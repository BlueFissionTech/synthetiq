<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Examples;

use BlueFission\Arr;
use BlueFission\Data\File;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use PHPUnit\Framework\TestCase;

final class ExamplesSmokeTest extends TestCase
{
    public function testExampleScriptsRunAgainstCurrentApis(): void
    {
        $root = dirname(__DIR__, 2);
        $scripts = [
            ['examples/minimal.php', ['hello'], 'Intent:'],
            ['examples/batch.php', ['1'], 'Intent:'],
            ['examples/envelope.php', ['hello'], '"response"'],
            ['examples/evaluator.php', ['--no-progress'], 'Accuracy:'],
            ['benchmarks/selection.php', ['3', '2', '1'], 'Throughput:'],
        ];

        foreach ($scripts as $script) {
            $result = $this->runPhp($root, (string)$script[0], $script[1]);
            $this->assertSame(0, $result['exit'], $result['stderr']);
            $this->assertStringContainsString((string)$script[2], $result['stdout']);
        }
    }

    public function testRouteStateExampleWritesVerifiesAndAppliesState(): void
    {
        $root = dirname(__DIR__, 2);
        $statePath = $root . '/models/routes/example_smoke_routes.json';
        $file = new File();
        if ($file->exists($statePath)) {
            @unlink($statePath);
        }

        $result = $this->runPhp($root, 'examples/route_state.php', [
            '--write',
            '--state=' . $statePath,
            '--apply',
            '--probe=hello',
        ]);

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $payload = HTTP::jsonDecode($result['stdout'], true, []);
        $this->assertIsArray($payload);
        $this->assertTrue((bool)($payload['wrote'] ?? false));
        $this->assertTrue((bool)($payload['matches'] ?? false));
        $this->assertNotSame('', (string)($payload['apply']['response'] ?? ''));
        $this->assertNotSame('', (string)($payload['apply']['intent'] ?? ''));

        if ($file->exists($statePath)) {
            @unlink($statePath);
        }
    }

    /**
     * @param array<int, string> $args
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runPhp(string $root, string $script, array $args = []): array
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for example smoke tests.');
        }

        $command = Arr::merge([PHP_BINARY, $root . '/' . Str::replace($script, '/', DIRECTORY_SEPARATOR)], $args);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $root);
        if (!is_resource($process)) {
            $this->fail('Unable to start example process.');
        }

        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exit' => (int)proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
