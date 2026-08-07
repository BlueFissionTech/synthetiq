<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Profiles;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class ConversationProfile extends Obj
{
    public const VERSION = 1;

    protected $_data = [
        'version' => self::VERSION,
        'id' => '',
        'identity' => [],
        'role' => '',
        'domain_knowledge_refs' => [],
        'conversational_policies' => [],
        'supported_intents' => [],
        'declared_capabilities' => [],
        'context_permissions' => [],
    ];

    protected $_types = [
        'version' => DataTypes::INTEGER,
        'id' => DataTypes::STRING,
        'identity' => DataTypes::ARRAY,
        'role' => DataTypes::STRING,
        'domain_knowledge_refs' => DataTypes::ARRAY,
        'conversational_policies' => DataTypes::ARRAY,
        'supported_intents' => DataTypes::ARRAY,
        'declared_capabilities' => DataTypes::ARRAY,
        'context_permissions' => DataTypes::ARRAY,
    ];

    public function __construct(array $profile = [])
    {
        parent::__construct();

        $profile = Dev::apply('synthetiq.profile.input', $profile);
        if (Arr::is($profile)) {
            $this->assign(self::normalize($profile));
        }

        Dev::do('synthetiq.profile.created', $this->toArray());
    }

    public static function fromArray(array $profile): self
    {
        return new self($profile);
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return self::validate($this->toArray());
    }

    public function isValid(): bool
    {
        return Val::isEmpty($this->errors());
    }

    public function id(): string
    {
        return (string)$this->field('id');
    }

    /**
     * @return array<int, string>
     */
    public function contextPermissions(): array
    {
        $permissions = $this->field('context_permissions');

        return Arr::is($permissions) ? $permissions : [];
    }

    public function supportsIntent(string $intent): bool
    {
        return Arr::has($this->stringField('supported_intents'), self::text($intent), true);
    }

    public function declaresCapability(string $capability): bool
    {
        return Arr::has($this->stringField('declared_capabilities'), self::text($capability), true);
    }

    public function permitsContextReference(string $reference): bool
    {
        return Arr::has($this->contextPermissions(), self::text($reference), true);
    }

    /**
     * @return array<int, string>
     */
    public static function validate(array $profile): array
    {
        $errors = [];
        if (Val::isEmpty(self::text($profile['id'] ?? ''))) {
            $errors[] = 'id_required';
        }

        $identity = Arr::is($profile['identity'] ?? null) ? $profile['identity'] : [];
        if (Val::isEmpty(self::text($identity['name'] ?? ''))) {
            $errors[] = 'identity_name_required';
        }

        if (Val::isEmpty(self::text($profile['role'] ?? ''))) {
            $errors[] = 'role_required';
        }

        if (Val::isEmpty($profile['supported_intents'] ?? [])) {
            $errors[] = 'supported_intents_required';
        }

        if (Val::isEmpty($profile['declared_capabilities'] ?? [])) {
            $errors[] = 'declared_capabilities_required';
        }

        if (!Arr::is($profile['context_permissions'] ?? null)) {
            $errors[] = 'context_permissions_required';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $profile): array
    {
        $identity = Arr::is($profile['identity'] ?? null) ? $profile['identity'] : [];
        $normalized = [
            'version' => self::VERSION,
            'id' => self::text($profile['id'] ?? ''),
            'identity' => [
                'name' => self::text($identity['name'] ?? ''),
                'description' => self::text($identity['description'] ?? ''),
            ],
            'role' => self::text($profile['role'] ?? ''),
            'domain_knowledge_refs' => self::stringList($profile['domain_knowledge_refs'] ?? []),
            'conversational_policies' => Arr::is($profile['conversational_policies'] ?? null)
                ? $profile['conversational_policies']
                : [],
            'supported_intents' => self::stringList($profile['supported_intents'] ?? []),
            'declared_capabilities' => self::stringList($profile['declared_capabilities'] ?? []),
            'context_permissions' => self::stringList($profile['context_permissions'] ?? []),
        ];

        $filtered = Dev::apply('synthetiq.profile.normalized', $normalized);

        return Arr::is($filtered) ? $filtered : $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected function stringField(string $field): array
    {
        $value = $this->field($field);

        return Arr::is($value) ? $value : [];
    }

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    protected static function stringList(mixed $values): array
    {
        if (!Arr::is($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            $value = self::text($value);
            if (Val::isNotEmpty($value) && !Arr::has($list, $value, true)) {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected static function text(mixed $value): string
    {
        return Str::trim((string)$value);
    }
}
