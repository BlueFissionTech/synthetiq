<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Jenss;

use PHPUnit\Framework\TestCase;

final class JenssStressFixtureTest extends TestCase
{
    public function testStressFixturesRunThroughJeneratorWhenAvailable(): void
    {
        $autoload = getenv('SYNTHETIQ_JENERATOR_AUTOLOAD') ?: getenv('JENERATOR_AUTOLOAD');
        if (!is_string($autoload) || $autoload === '' || !is_file($autoload)) {
            $this->markTestSkipped('Set SYNTHETIQ_JENERATOR_AUTOLOAD to a Jenerator vendor/autoload.php path to run JenSS fixture smoke tests.');
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for isolated JenSS fixture smoke tests.');
        }

        $projectRoot = dirname(__DIR__, 2);
        $config = require $projectRoot . '/sample_configs/jenss_stress.php';
        $scripts = $config['scripts'] ?? [];

        $this->assertNotEmpty($scripts, 'Expected at least one configured JenSS stress fixture.');

        foreach ($scripts as $script) {
            $path = $projectRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)($script['path'] ?? ''));
            $expected = $script['expected_messages'] ?? [];

            $this->assertFileExists($path);
            $this->assertIsArray($expected);
            $this->assertNotEmpty($expected, 'Expected messages must be configured for JenSS fixture smoke validation.');

            $messages = $this->runFixtureInIsolatedPhp($projectRoot, $autoload, $path);

            $this->assertSame($expected, $messages, 'Unexpected JenSS fixture output for ' . (string)$script['path']);
        }
    }

    /**
     * @return array<int, string>
     */
    private function runFixtureInIsolatedPhp(string $projectRoot, string $autoload, string $path): array
    {
        $runner = tempnam(sys_get_temp_dir(), 'synthetiq_jenss_');
        $this->assertIsString($runner);

        $source = <<<'PHP'
<?php

declare(strict_types=1);

[$runner, $projectRoot, $autoload, $path] = $argv;

chdir($projectRoot);
require_once $autoload;

$parser = new BlueFission\Jenerator\Parsing\JenssParser();
$ast = $parser->parseFile($path);
$io = new BlueFission\Jenerator\Runtime\Io\CollectingIo();
$interpreter = new BlueFission\Jenerator\Runtime\Interpreter($io);
$interpreter->run($ast);

echo json_encode($io->messages(), JSON_UNESCAPED_SLASHES);

PHP;

        file_put_contents($runner, $source);

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $runner, $projectRoot, $autoload, $path], $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($runner);
            $this->fail('Unable to start isolated PHP process for JenSS fixture validation.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        @unlink($runner);

        $this->assertSame(0, $exitCode, 'Jenerator fixture runner failed: ' . $stderr);

        $decoded = json_decode((string)$stdout, true);
        $this->assertIsArray($decoded, 'Jenerator fixture runner did not return a JSON message array. Output: ' . (string)$stdout);

        return array_values(array_map(static fn($message): string => (string)$message, $decoded));
    }
}
