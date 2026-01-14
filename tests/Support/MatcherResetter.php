<?php

namespace BlueFission\SynthetIQ\Tests\Support;

use BlueFission\Automata\Intent\Matcher;
use ReflectionClass;

class MatcherResetter
{
    public static function reset(): void
    {
        $reflection = new ReflectionClass(Matcher::class);
        $properties = ['skills', 'intents', 'intentSkillMap'];

        foreach ($properties as $property_name) {
            $property = $reflection->getProperty($property_name);
            $property->setAccessible(true);
            $property->setValue([]);
        }
    }
}
