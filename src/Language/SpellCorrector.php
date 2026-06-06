<?php

namespace BlueFission\SynthetIQ\Language;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\DevElation as Dev;

class SpellCorrector
{
    protected array $_vocabulary = [];
    protected bool $_enabled = true;
    protected int $_minTokenLength = 4;
    protected int $_maxDistance = 2;
    protected float $_minSimilarity = 0.75;
    protected int $_maxVocabulary = 5000;

    public function __construct(array $options = [])
    {
        if (isset($options['enabled'])) {
            $this->_enabled = (bool)$options['enabled'];
        }
        if (isset($options['min_token_length'])) {
            $this->_minTokenLength = max(1, (int)$options['min_token_length']);
        }
        if (isset($options['max_distance'])) {
            $this->_maxDistance = max(0, (int)$options['max_distance']);
        }
        if (isset($options['min_similarity'])) {
            $this->_minSimilarity = max(0.0, min(1.0, (float)$options['min_similarity']));
        }
        if (isset($options['max_vocabulary'])) {
            $this->_maxVocabulary = max(1, (int)$options['max_vocabulary']);
        }
    }

    public function enable(bool $enabled): void
    {
        $this->_enabled = $enabled;
    }

    public function setVocabulary(array $terms): void
    {
        $this->_vocabulary = [];
        $this->addTerms($terms);
    }

    public function addTerms(array $terms): void
    {
        foreach ($terms as $term) {
            $token = $this->normalizeToken((string)$term);
            if ($token === '') {
                continue;
            }
            $this->_vocabulary[$token] = true;
            if (count($this->_vocabulary) >= $this->_maxVocabulary) {
                break;
            }
        }
    }

    public function addText(string $text): void
    {
        $tokens = $this->tokenize($text);
        $this->addTerms($tokens);
    }

    public function normalize(string $input): string
    {
        $input = Dev::apply('synthetiq.spellcorrector.normalize.1', $input);

        if (!$this->_enabled || empty($this->_vocabulary)) {
            return $input;
        }

        $parts = preg_split('/([^\p{L}\p{N}]+)/u', $input, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $input;
        }

        $updated = [];
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[^\p{L}\p{N}]+$/u', $part)) {
                $updated[] = $part;
                continue;
            }

            $corrected = $this->correctToken($part);
            $updated[] = $corrected;
        }

        $output = implode('', $updated);
        $output = Dev::apply('synthetiq.spellcorrector.normalize.2', $output);
        Dev::do('synthetiq.spellcorrector.normalize.action1', [
            'input' => $input,
            'output' => $output,
        ]);

        return $output;
    }

    protected function correctToken(string $token): string
    {
        $normalized = $this->normalizeToken($token);
        if ($normalized === '') {
            return $token;
        }

        if (isset($this->_vocabulary[$normalized])) {
            return $token;
        }

        if (Str::len($normalized) < $this->_minTokenLength) {
            return $token;
        }

        if (preg_match('/\\d/', $normalized)) {
            return $token;
        }

        $best = null;
        $bestDistance = $this->_maxDistance + 1;
        $bestSimilarity = 0.0;

        foreach ($this->_vocabulary as $candidate => $value) {
            if ($candidate === $normalized) {
                return $token;
            }

            $lengthDelta = abs(Str::len($candidate) - Str::len($normalized));
            if ($lengthDelta > $this->_maxDistance) {
                continue;
            }

            $distance = levenshtein($normalized, $candidate);
            if ($distance > $this->_maxDistance) {
                continue;
            }

            $maxLength = max(Str::len($candidate), Str::len($normalized));
            $similarity = $maxLength > 0 ? 1.0 - ($distance / $maxLength) : 1.0;

            if ($similarity < $this->_minSimilarity) {
                continue;
            }

            if ($distance < $bestDistance || ($distance === $bestDistance && $similarity > $bestSimilarity)) {
                $best = $candidate;
                $bestDistance = $distance;
                $bestSimilarity = $similarity;
            }
        }

        if ($best === null) {
            return $token;
        }

        return $this->applyCase($token, $best);
    }

    protected function normalizeToken(string $token): string
    {
        $token = Str::lower(Str::trim($token));
        if ($token === '') {
            return '';
        }

        $token = preg_replace('/^\\W+|\\W+$/u', '', $token);
        if (!is_string($token)) {
            return '';
        }

        return Str::lower($token);
    }

    protected function tokenize(string $text): array
    {
        $parts = preg_split('/[^\\p{L}\\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        $tokens = (new Collection($parts))
            ->map(function ($token) {
                return $this->normalizeToken((string)$token);
            })
            ->filter(function ($token) {
                return $token !== '';
            })
            ->toArray();

        return Arr::unique($tokens);
    }

    protected function applyCase(string $original, string $replacement): string
    {
        if ($replacement === '') {
            return $original;
        }

        $first = $original[0] ?? '';
        if ($first !== '' && strtoupper($first) === $first) {
            return ucfirst($replacement);
        }

        return $replacement;
    }
}
