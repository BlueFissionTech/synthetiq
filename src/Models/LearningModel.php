<?php

namespace BlueFission\SynthetIQ\Models;

use BlueFission\Automata\Context;
use BlueFission\Automata\Language\MarkovPredictor;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Str;
use BlueFission\Arr;

class LearningModel
{
    protected $markov;
    protected $memory;
    protected $starters;
    protected $fragments;
    protected $max_sentence_length = 24;
    protected $min_sentence_length = 4;

    public function __construct(array $options = [])
    {
        $this->markov = new MarkovPredictor();
        $this->memory = [];
        $this->starters = new OrganizedCollection();
        $this->starters->setMax(2000);
        $this->fragments = new OrganizedCollection();
        $this->fragments->setMax(5000);

        if (isset($options['max_sentence_length'])) {
            $this->max_sentence_length = (int)$options['max_sentence_length'];
        }
        if (isset($options['min_sentence_length'])) {
            $this->min_sentence_length = (int)$options['min_sentence_length'];
        }
    }

    public function observe(string $input, string $response, ?Context $context = null): void
    {
        $input = $this->normalize($input);
        $response = Str::trim($response);

        if ($input === '' || $response === '') {
            return;
        }

        $this->markov->addSentence($response);

        $tokens = $this->tokenize($response);
        $this->rememberStarter($tokens);
        $this->rememberFragments($tokens);

        if (!isset($this->memory[$input])) {
            $this->memory[$input] = new OrganizedCollection();
            $this->memory[$input]->setMax(2000);
        }

        $this->memory[$input]->add($response, $response, 1);
    }

    public function train(array $interactions): void
    {
        foreach ($interactions as $interaction) {
            if (!isset($interaction['input'], $interaction['output'])) {
                continue;
            }
            $this->observe($interaction['input'], $interaction['output']);
        }
    }

    public function generate(string $input, ?Context $context = null): string
    {
        $normalized = $this->normalize($input);

        if ($normalized !== '' && isset($this->memory[$normalized])) {
            $response = $this->pickWeighted($this->memory[$normalized]);
            if ($response !== null) {
                return $response;
            }
        }

        $closest = $this->findClosestInput($normalized);
        if ($closest && isset($this->memory[$closest])) {
            $response = $this->pickWeighted($this->memory[$closest]);
            if ($response !== null) {
                return $response;
            }
        }

        return $this->generateFromMarkov($input);
    }

    public function saveModel(string $filePath): void
    {
        $payload = [
            'memory' => $this->serializeMemory(),
            'starters' => $this->starters->contents(),
            'fragments' => $this->fragments->contents(),
            'markov' => $this->markov->serializeModel(),
        ];

        file_put_contents($filePath, serialize($payload));
    }

    public function loadModel(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $payload = unserialize(file_get_contents($filePath));
        if (!is_array($payload)) {
            return;
        }

        $this->memory = $this->restoreMemory($payload['memory'] ?? []);
        $this->starters = $this->restoreCollection($payload['starters'] ?? []);
        $this->fragments = $this->restoreCollection($payload['fragments'] ?? []);

        if (isset($payload['markov'])) {
            $this->markov->deserializeModel($payload['markov']);
        }
    }

    protected function normalize(string $text): string
    {
        $text = Str::lower(Str::trim($text));
        $text = preg_replace("/[^a-z0-9\\s\\-'.!?]/", '', $text);

        return Str::trim(preg_replace('/\\s+/', ' ', $text));
    }

    protected function tokenize(string $text): array
    {
        $tokens = Str::split($text);
        return array_values(array_filter($tokens, function ($token) {
            return $token !== '';
        }));
    }

    protected function rememberStarter(array $tokens): void
    {
        if (empty($tokens)) {
            return;
        }

        $starter = $tokens[0];
        if (count($tokens) > 1) {
            $starter .= ' ' . $tokens[1];
        }

        $this->starters->add($starter, $starter, 1);
    }

    protected function rememberFragments(array $tokens): void
    {
        $count = count($tokens);
        if ($count < 2) {
            return;
        }

        for ($i = 0; $i < $count - 1; $i++) {
            $fragment = $tokens[$i] . ' ' . $tokens[$i + 1];
            $this->fragments->add($fragment, $fragment, 1);
        }

        if ($count > 2) {
            for ($i = 0; $i < $count - 2; $i++) {
                $fragment = $tokens[$i] . ' ' . $tokens[$i + 1] . ' ' . $tokens[$i + 2];
                $this->fragments->add($fragment, $fragment, 1);
            }
        }
    }

    protected function generateFromMarkov(string $input): string
    {
        $seed = $this->pickWeighted($this->starters);
        if ($seed === null) {
            $tokens = $this->tokenize($input);
            $seed = $tokens[0] ?? '';
        }

        if ($seed === '') {
            return '';
        }

        $words = preg_split('/\\s+/', Str::trim($seed));
        $sentence = $seed;

        while (count($words) < $this->max_sentence_length) {
            $next = $this->markov->predictNextWord(end($words));
            if (!$next) {
                break;
            }

            $sentence .= ' ' . $next;
            $words[] = $next;

            if (preg_match('/[.!?]$/', $sentence) && count($words) >= $this->min_sentence_length) {
                break;
            }
        }

        return Str::trim($sentence);
    }

    protected function findClosestInput(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }

        $tokens = $this->tokenize($normalized);
        $bestScore = 0;
        $bestKey = null;

        foreach ($this->memory as $key => $responses) {
            $keyTokens = $this->tokenize($key);
            if (empty($keyTokens)) {
                continue;
            }

            $common = Arr::intersect($tokens, $keyTokens);
            $score = count($common) / max(count($tokens), count($keyTokens));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        return $bestScore >= 0.3 ? $bestKey : null;
    }

    protected function pickWeighted(OrganizedCollection $collection): ?string
    {
        $entries = $collection->contents();
        if (empty($entries)) {
            return null;
        }

        $total = 0;
        foreach ($entries as $entry) {
            $total += $entry['weight'] ?? 0;
        }

        if ($total <= 0) {
            return $collection->rand();
        }

        $rand = mt_rand(0, $total - 1);
        foreach ($entries as $entry) {
            $rand -= $entry['weight'] ?? 0;
            if ($rand < 0) {
                return $entry['value'] ?? null;
            }
        }

        return $entries[array_key_first($entries)]['value'] ?? null;
    }

    protected function serializeMemory(): array
    {
        $serialized = [];
        foreach ($this->memory as $key => $collection) {
            $serialized[$key] = $collection->contents();
        }

        return $serialized;
    }

    protected function restoreMemory(array $payload): array
    {
        $memory = [];
        foreach ($payload as $key => $entries) {
            $memory[$key] = $this->restoreCollection($entries);
        }

        return $memory;
    }

    protected function restoreCollection(array $entries): OrganizedCollection
    {
        $collection = new OrganizedCollection();
        $collection->setMax(2000);

        foreach ($entries as $key => $entry) {
            $value = $entry['value'] ?? $key;
            $weight = $entry['weight'] ?? 1;
            $collection->add($value, $value, $weight);
        }

        return $collection;
    }
}
