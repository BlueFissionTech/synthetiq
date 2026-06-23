<?php

namespace BlueFission\SynthetIQ\Tests\Language;

use BlueFission\SynthetIQ\Language\SpellCorrector;
use PHPUnit\Framework\TestCase;

class SpellCorrectorTest extends TestCase
{
    public function testCorrectsNearMatchToken(): void
    {
        $corrector = new SpellCorrector([
            'max_distance' => 2,
            'min_similarity' => 0.6,
        ]);
        $corrector->addTerms(['hello', 'world']);

        $result = $corrector->normalize('hellp world');

        $this->assertSame('hello world', $result);
    }

    public function testDoesNotCorrectWhenDisabled(): void
    {
        $corrector = new SpellCorrector(['enabled' => false]);
        $corrector->addTerms(['hello']);

        $result = $corrector->normalize('hellp');

        $this->assertSame('hellp', $result);
    }
}
