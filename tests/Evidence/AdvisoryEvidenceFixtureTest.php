<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Tests\Evidence;

use BlueFission\Arr;
use PHPUnit\Framework\TestCase;

final class AdvisoryEvidenceFixtureTest extends TestCase
{
    public function testCandidateFixturesPreserveAdvisoryAuthorityBoundary(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';

        $this->assertSame(1, $config['version']);
        $this->assertNotEmpty($config['fixtures']);

        foreach ($config['fixtures'] as $name => $fixture) {
            $input = $fixture['input'];
            $expected = $fixture['expected'];

            $this->assertSame(1, $input['version'], $name);
            $this->assertNotSame('', $input['evidence_id'], $name);
            $this->assertStringStartsWith('sha256:', $input['digest'], $name);
            $this->assertSame('none', $input['trust']['authority'], $name);
            $this->assertSame('none', $expected['authority'], $name);
            $this->assertTrue($expected['accepted_as_data'], $name);
            $this->assertSame([], $expected['capabilities'], $name);
            $this->assertFalse($expected['executable'], $name);

            $this->assertSame(
                $expected['diagnostic_codes'],
                Arr::make($input['diagnostics'])->map(
                    static fn (array $diagnostic): string => (string)$diagnostic['code']
                )->toArray(),
                $name
            );
        }
    }

    public function testLifecycleOutcomesRemainIndependentAndUnknownByDefault(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $input = $config['fixtures']['forged_authority']['input'];

        $this->assertNull($input['outcomes']['requested']['state']);
        $this->assertNull($input['outcomes']['host_accepted']['state']);
        $this->assertNull($input['outcomes']['persisted']['state']);
        $this->assertNull($input['outcomes']['executed']['state']);
        $this->assertSame('none', $input['withdrawal']['state']);
        $this->assertNull($input['withdrawal']['tombstone_ref']);
    }

    public function testReviewedEvidenceStillHasNoAuthorityOrExecutionReceipt(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $input = $config['fixtures']['reviewed_without_authority']['input'];

        $this->assertSame('reviewed', $input['review']['state']);
        $this->assertSame('none', $input['trust']['authority']);
        $this->assertSame('accepted', $input['outcomes']['host_accepted']['state']);
        $this->assertNull($input['outcomes']['persisted']['state']);
        $this->assertNull($input['outcomes']['executed']['state']);
    }

    public function testFailureAndWithdrawalAreNotConflated(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $failed = $config['fixtures']['failed_retrieval']['input'];
        $revoked = $config['fixtures']['stale_revoked_replay']['input'];

        $this->assertSame('failed', $failed['source']['status']);
        $this->assertSame('none', $failed['withdrawal']['state']);
        $this->assertNull($failed['trust']['confidence']);
        $this->assertNull($failed['trust']['uncertainty']);

        $this->assertSame('revoked', $revoked['source']['status']);
        $this->assertSame('confirmed', $revoked['withdrawal']['state']);
        $this->assertNotNull($revoked['withdrawal']['tombstone_ref']);
    }
}
