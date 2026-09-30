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
        $this->assertSame('synthetiq-advisory-evidence-candidate-v1', $config['canonicalization']);
        $this->assertNotEmpty($config['fixtures']);

        foreach ($config['fixtures'] as $name => $fixture) {
            $input = $fixture['input'];
            $expected = $fixture['expected'];

            $this->assertSame(1, $input['version'], $name);
            $this->assertNotSame('', $input['evidence_id'], $name);
            $this->assertStringStartsWith('sha256:', $input['digest'], $name);
            $this->assertSame($this->digest($input), $input['digest'], $name);
            $this->assertSame('none', $input['trust']['authority'], $name);
            $this->assertSame('none', $expected['authority'], $name);
            $this->assertTrue($expected['captured_for_diagnostics'], $name);
            $this->assertFalse($expected['admitted_to_context'], $name);
            $this->assertSame([], $expected['capabilities'], $name);
            $this->assertFalse($expected['executable'], $name);
            $this->assertFalse($expected['routing_effect'], $name);
            $this->assertFalse($expected['host_acceptance_verified'], $name);

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
        $this->assertNull($input['withdrawal']['projection_of']);
        $this->assertNull($input['withdrawal']['tombstone_ref']);
        $this->assertNull($input['withdrawal']['tombstone']);
        $this->assertSame([], $input['withdrawal']['tombstone_candidates']);
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
        $this->assertFalse($config['fixtures']['reviewed_without_authority']['expected']['host_acceptance_verified']);
        $this->assertFalse($config['fixtures']['reviewed_without_authority']['expected']['admitted_to_context']);
    }

    public function testFailureAndWithdrawalAreNotConflated(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $failed = $config['fixtures']['failed_retrieval']['input'];
        $revoked = $config['fixtures']['stale_revoked_replay']['input'];
        $withdrawn = $config['fixtures']['explicit_withdrawal']['input'];

        $this->assertSame('failed', $failed['source']['status']);
        $this->assertSame('none', $failed['withdrawal']['state']);
        $this->assertNull($failed['trust']['confidence']);
        $this->assertNull($failed['trust']['uncertainty']);

        $this->assertSame('revoked', $revoked['source']['status']);
        $this->assertSame('none', $revoked['withdrawal']['state']);
        $this->assertNull($revoked['withdrawal']['tombstone_ref']);

        $this->assertSame('available', $withdrawn['source']['status']);
        $this->assertSame('confirmed', $withdrawn['withdrawal']['state']);
        $this->assertNotNull($withdrawn['withdrawal']['tombstone_ref']);
    }

    public function testTombstoneProjectionRejectsScopeTargetAndOrderingFailures(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $target = $config['fixtures']['withdrawal_target']['input'];
        $valid = $config['fixtures']['explicit_withdrawal']['input']['withdrawal']['tombstone'];

        $this->assertSame($target['evidence_id'], $valid['target_evidence_id']);
        $this->assertSame($target['digest'], $valid['target_digest']);
        $this->assertSame(
            'tombstone_scope_mismatch',
            $config['fixtures']['wrong_scope_tombstone']['input']['diagnostics'][0]['code']
        );
        $this->assertSame(
            'tombstone_target_mismatch',
            $config['fixtures']['wrong_target_digest_tombstone']['input']['diagnostics'][0]['code']
        );
        $this->assertSame(
            ['tombstone_order_invalid', 'duplicate_tombstone_ignored'],
            array_column($config['fixtures']['duplicate_reordered_tombstones']['input']['diagnostics'], 'code')
        );
    }

    public function testConfirmedWithdrawalRequiresTargetBoundProjection(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $target = $config['fixtures']['withdrawal_target']['input'];

        foreach (['explicit_withdrawal', 'source_recovery_non_resurrection'] as $name) {
            $projection = $config['fixtures'][$name]['input'];
            $binding = $projection['withdrawal']['projection_of'];
            $tombstone = $projection['withdrawal']['tombstone'];

            $this->assertSame('confirmed', $projection['withdrawal']['state'], $name);
            $this->assertSame($target['evidence_id'], $projection['evidence_id'], $name);
            $this->assertSame($target['evidence_id'], $binding['evidence_id'], $name);
            $this->assertSame($target['digest'], $binding['digest'], $name);
            $this->assertSame($binding['evidence_id'], $tombstone['target_evidence_id'], $name);
            $this->assertSame($binding['digest'], $tombstone['target_digest'], $name);
        }

        $unbound = $config['fixtures']['unbound_withdrawal_projection'];
        $this->assertNotSame($target['evidence_id'], $unbound['input']['evidence_id']);
        $this->assertSame('rejected', $unbound['input']['withdrawal']['state']);
        $this->assertSame(
            ['tombstone_projection_mismatch'],
            $unbound['expected']['diagnostic_codes']
        );
        $this->assertFalse($unbound['expected']['admitted_to_context']);
    }

    public function testWithdrawalBlocksDerivedAndRecoveredSourceRecords(): void
    {
        $config = require dirname(__DIR__, 2) . '/sample_configs/advisory_evidence.php';
        $target = $config['fixtures']['withdrawal_target']['input'];
        $derived = $config['fixtures']['derived_after_withdrawal'];
        $recovered = $config['fixtures']['source_recovery_non_resurrection'];

        $this->assertSame([$target['evidence_id']], $derived['input']['lineage']['parent_ids']);
        $this->assertSame([$target['digest']], $derived['input']['lineage']['parent_digests']);
        $this->assertFalse($derived['expected']['admitted_to_context']);
        $this->assertSame('available', $recovered['input']['source']['status']);
        $this->assertSame('current', $recovered['input']['trust']['freshness']);
        $this->assertSame('confirmed', $recovered['input']['withdrawal']['state']);
        $this->assertSame($target['evidence_id'], $recovered['input']['evidence_id']);
        $this->assertSame($target['digest'], $recovered['input']['withdrawal']['projection_of']['digest']);
        $this->assertFalse($recovered['expected']['admitted_to_context']);
    }

    private function digest(array $envelope): string
    {
        unset($envelope['digest']);

        return 'sha256:' . hash('sha256', json_encode(
            $this->canonicalize($envelope),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
