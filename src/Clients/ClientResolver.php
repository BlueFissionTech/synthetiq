<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Clients;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Val;
use RuntimeException;

class ClientResolver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = [])
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function make(array $config = []): self
    {
        return new self($config);
    }

    public function client(string $interface): ?object
    {
        if (!$this->isEnabled($interface)) {
            return null;
        }

        $binding = $this->binding($interface);
        if ($binding === null) {
            return null;
        }

        return $this->resolve($interface, $binding);
    }

    public function isEnabled(string $interface): bool
    {
        $enabled = $this->enabledConfig();
        if (!Arr::hasKey($enabled, $interface)) {
            return true;
        }

        return Flag::parseBool($enabled[$interface], true);
    }

    protected function binding(string $interface): mixed
    {
        $bindings = $this->bindingsConfig();

        return $bindings[$interface] ?? null;
    }

    protected function resolve(string $interface, mixed $binding): ?object
    {
        if ($binding instanceof $interface) {
            return $binding;
        }

        if (Func::isCallable($binding)) {
            $binding = $binding();
            if ($binding === null || $binding instanceof $interface) {
                return $binding;
            }
        }

        if (Str::is($binding) && Val::isNotEmpty($binding)) {
            if (!class_exists($binding)) {
                return null;
            }

            $client = new $binding();
            if ($client instanceof $interface) {
                return $client;
            }
        }

        throw new RuntimeException("Client binding does not implement {$interface}.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function bindingsConfig(): array
    {
        if (Arr::is($this->config['bindings'] ?? null)) {
            return $this->config['bindings'];
        }

        return $this->config;
    }

    /**
     * @return array<string, bool>
     */
    protected function enabledConfig(): array
    {
        return Arr::is($this->config['enabled'] ?? null) ? $this->config['enabled'] : [];
    }
}
