<?php

namespace BlueFission\SynthetIQ\Tests\Language;

use BlueFission\SynthetIQ\Language\BoundedTrigramPredictor;
use PHPUnit\Framework\TestCase;

class BoundedTrigramPredictorTest extends TestCase
{
    public function testPredictsNextWordFromTrigramState(): void
    {
        $predictor = new BoundedTrigramPredictor();
        $predictor->addSentence('hub road open');
        $predictor->addSentence('hub road closed');
        $predictor->addSentence('hub road closed');

        mt_srand(2024);
        $next = $predictor->predictNextWord('hub road');

        $this->assertContains($next, ['open', 'closed']);
        $this->assertSame(['closed', 'open'], $predictor->predictNextWords('hub road'));
    }
}
