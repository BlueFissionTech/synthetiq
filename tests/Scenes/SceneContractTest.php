<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Scenes;

use BlueFission\Arr;
use BlueFission\SynthetIQ\Scenes\SceneContract;
use PHPUnit\Framework\TestCase;

final class SceneContractTest extends TestCase
{
    public function testSampleSceneContractsAreValid(): void
    {
        $scenes = require dirname(__DIR__, 2) . '/sample_configs/conversation_scenes.php';

        $this->assertSame(['proof_walkthrough', 'operator_assistant'], Arr::keys($scenes));

        foreach ($scenes as $scene) {
            $this->assertSame([], SceneContract::validate($scene));
            $this->assertTrue(SceneContract::isValid($scene));
        }
    }

    public function testDetectsMissingSafetyAndVoicePolicy(): void
    {
        $errors = SceneContract::validate([
            'id' => 'missing_policy',
            'states' => [
                [
                    'id' => 'intro',
                    'prompt' => 'Start.',
                    'fallback' => ['prompt' => 'Retry.', 'next' => 'intro'],
                ],
            ],
        ]);

        $this->assertContains('Scene voice_policy is required.', $errors);
        $this->assertContains('Scene public_safety is required.', $errors);
    }

    public function testDetectsBrokenChoiceTransitions(): void
    {
        $scene = $this->validScene();
        $scene['states'][0]['choices'][] = [
            'id' => 'missing',
            'label' => 'Missing',
            'next' => 'not_found',
        ];

        $this->assertContains(
            'Scene state intro choice missing points to missing state not_found.',
            SceneContract::validate($scene)
        );
    }

    public function testNormalizesStateDefaultsAndIndexesStates(): void
    {
        $scene = [
            'id' => ' simple ',
            'voice_policy' => ['tone' => 'neutral', 'allowed' => ['answer']],
            'public_safety' => [
                'constraints' => ['stay factual'],
                'escalation' => ['handoff' => 'review'],
            ],
            'states' => [
                [
                    'id' => ' intro ',
                    'prompt' => 'Hello',
                    'fallback' => ['prompt' => 'Retry'],
                ],
            ],
        ];

        $normalized = SceneContract::normalize($scene);

        $this->assertSame(SceneContract::VERSION, $normalized['version']);
        $this->assertSame('simple', $normalized['id']);
        $this->assertSame('intro', $normalized['states'][0]['id']);
        $this->assertSame(SceneContract::TYPE_DIALOGUE, $normalized['states'][0]['type']);
        $this->assertSame(['intro' => $scene['states'][0]], SceneContract::statesById($scene));
    }

    /**
     * @return array<string, mixed>
     */
    private function validScene(): array
    {
        return [
            'id' => 'valid_scene',
            'voice_policy' => [
                'tone' => 'calm',
                'allowed' => ['answer from configured material'],
            ],
            'public_safety' => [
                'constraints' => ['do not collect secrets'],
                'escalation' => ['handoff' => 'review'],
            ],
            'states' => [
                [
                    'id' => 'intro',
                    'type' => SceneContract::TYPE_DECISION,
                    'prompt' => 'Choose a path.',
                    'choices' => [
                        ['id' => 'continue', 'label' => 'Continue', 'next' => 'done'],
                    ],
                    'fallback' => ['prompt' => 'Choose again.', 'next' => 'intro'],
                ],
                [
                    'id' => 'done',
                    'type' => SceneContract::TYPE_DIALOGUE,
                    'prompt' => 'Done.',
                    'fallback' => ['prompt' => 'Restart.', 'next' => 'intro'],
                ],
            ],
        ];
    }
}
