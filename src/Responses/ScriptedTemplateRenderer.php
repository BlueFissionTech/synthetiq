<?php

namespace BlueFission\SynthetIQ\Responses;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

class ScriptedTemplateRenderer
{
    protected int $maxBlocks = 25;
    protected array $lastDiagnostics = [];

    public function render(string $template, array $variables): string
    {
        $this->lastDiagnostics = [
            'enabled' => true,
            'blocks' => [],
            'errors' => [],
        ];

        $output = $template;
        for ($i = 0; $i < $this->maxBlocks; $i++) {
            $match = Str::matchPattern($output, '/\{=\s*([^{}]+?)\s*\}/');
            if (!Arr::is($match) || Arr::count($match) < 2) {
                break;
            }

            $block = (string)$match[0];
            $expression = Str::trim((string)$match[1]);
            $value = $this->evaluate($expression, $variables);
            $this->lastDiagnostics['blocks'][] = [
                'expression' => $expression,
                'resolved' => Val::isNotEmpty($value),
            ];

            $output = Str::replace($output, $block, $value);
        }

        return $output;
    }

    public function lastDiagnostics(): array
    {
        return $this->lastDiagnostics;
    }

    protected function evaluate(string $expression, array $variables): string
    {
        $function = Str::matchPattern($expression, '/^([A-Za-z_][A-Za-z0-9_]*)\(([^()]*)\)$/');
        if (Arr::is($function) && Arr::count($function) === 3) {
            return $this->evaluateFunction((string)$function[1], Str::trim((string)$function[2]), $variables);
        }

        return $this->resolvePath($expression, $variables);
    }

    protected function evaluateFunction(string $name, string $path, array $variables): string
    {
        $value = $this->resolvePath($path, $variables);
        $name = Str::lower($name);

        return match ($name) {
            'upper' => Str::upper($value),
            'lower' => Str::lower($value),
            'trim' => Str::trim($value),
            'capitalize' => Str::capitalize($value),
            default => $this->invalid("Unsupported scripted template function '{$name}'."),
        };
    }

    protected function resolvePath(string $path, array $variables): string
    {
        $path = Str::trim($path);
        if (!Str::matches($path, '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)*$/')) {
            return $this->invalid("Invalid scripted template expression '{$path}'.");
        }

        $value = Arr::getPath($variables, $path, null);
        if ($value === null) {
            $this->lastDiagnostics['errors'][] = [
                'expression' => $path,
                'reason' => 'missing',
            ];
            return '';
        }

        if (Str::is($value) || Num::is($value)) {
            return (string)$value;
        }

        return '';
    }

    protected function invalid(string $message): string
    {
        $this->lastDiagnostics['errors'][] = [
            'reason' => 'invalid',
            'message' => $message,
        ];

        return '';
    }
}
