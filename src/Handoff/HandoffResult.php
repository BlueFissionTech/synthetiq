<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Handoff;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;

class HandoffResult extends Obj
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLARIFICATION = 'clarification';
    public const STATUS_FAILURE = 'failure';

    protected $_data = [
        'handoff_status' => self::STATUS_FAILURE,
        'profile_id' => '',
        'context' => [],
        'diagnostics' => [],
        'output_id' => '',
    ];

    protected $_types = [
        'handoff_status' => DataTypes::STRING,
        'profile_id' => DataTypes::STRING,
        'context' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'output_id' => DataTypes::STRING,
    ];

    public function __construct(
        string $status,
        string $profileId,
        array $context,
        array $diagnostics,
        string $outputId
    ) {
        parent::__construct();
        $this->assign([
            'handoff_status' => $status,
            'profile_id' => $profileId,
            'context' => $context,
            'diagnostics' => $diagnostics,
            'output_id' => $outputId,
        ]);
    }

    public function status(): string
    {
        return (string)$this->field('handoff_status');
    }

    public function isAccepted(): bool
    {
        return $this->status() === self::STATUS_ACCEPTED;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $context = $this->field('context');

        return Arr::is($context) ? $context : [];
    }

    /**
     * @return array<int, string>
     */
    public function diagnostics(): array
    {
        $diagnostics = $this->field('diagnostics');

        return Arr::is($diagnostics) ? $diagnostics : [];
    }

    public function outputId(): string
    {
        return (string)$this->field('output_id');
    }
}
