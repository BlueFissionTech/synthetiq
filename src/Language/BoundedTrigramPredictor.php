<?php

namespace BlueFission\SynthetIQ\Language;

class BoundedTrigramPredictor
{
    protected array $_states = [];
    protected array $_beginnings = [];
    protected int $_maxStates;
    protected int $_maxBeginnings;
    protected int $_maxTransitions;

    public function __construct(array $options = [])
    {
        $this->_maxStates = max(1, (int)($options['max_states'] ?? 10000));
        $this->_maxBeginnings = max(1, (int)($options['max_beginnings'] ?? 1000));
        $this->_maxTransitions = max(1, (int)($options['max_transitions'] ?? 100));
    }

    public function addSentence(string $sentence): void
    {
        $words = $this->tokenize($sentence);
        if (count($words) < 3) {
            return;
        }

        $this->rememberBeginning($words[0] . ' ' . $words[1]);

        $count = count($words);
        for ($i = 2; $i < $count; $i++) {
            $trigram = $words[$i - 2] . ' ' . $words[$i - 1];
            $nextWord = $words[$i];

            if (!isset($this->_states[$trigram]) && count($this->_states) >= $this->_maxStates) {
                unset($this->_states[array_key_first($this->_states)]);
            }

            if (!isset($this->_states[$trigram])) {
                $this->_states[$trigram] = [];
            }

            if (!isset($this->_states[$trigram][$nextWord]) && count($this->_states[$trigram]) >= $this->_maxTransitions) {
                $lowest = $this->lowestWeightedKey($this->_states[$trigram]);
                unset($this->_states[$trigram][$lowest]);
            }

            $this->_states[$trigram][$nextWord] = ($this->_states[$trigram][$nextWord] ?? 0) + 1;
        }
    }

    public function predictNextWord(string $sentence): ?string
    {
        $words = $this->tokenize($sentence);
        $previousTwoWords = implode(' ', array_slice($words, -2));
        if ($previousTwoWords === '' || empty($this->_states[$previousTwoWords])) {
            return null;
        }

        return $this->weightedPick($this->_states[$previousTwoWords]);
    }

    public function predictNextWords(string $sentence, int $limit = 5): array
    {
        $words = $this->tokenize($sentence);
        $previousTwoWords = implode(' ', array_slice($words, -2));
        if ($previousTwoWords === '' || empty($this->_states[$previousTwoWords])) {
            return [];
        }

        $candidates = $this->_states[$previousTwoWords];
        arsort($candidates);

        return array_slice(array_keys($candidates), 0, max(1, $limit));
    }

    public function tokenize(string $sentence): array
    {
        $tokens = preg_split('/\s+/', strtolower(trim($sentence)), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? $tokens : [];
    }

    protected function rememberBeginning(string $beginning): void
    {
        if (!isset($this->_beginnings[$beginning]) && count($this->_beginnings) >= $this->_maxBeginnings) {
            unset($this->_beginnings[array_key_first($this->_beginnings)]);
        }

        $this->_beginnings[$beginning] = ($this->_beginnings[$beginning] ?? 0) + 1;
    }

    protected function weightedPick(array $weights): ?string
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return null;
        }

        $rand = mt_rand(1, $total);
        foreach ($weights as $word => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                return (string)$word;
            }
        }

        return (string)array_key_first($weights);
    }

    protected function lowestWeightedKey(array $weights): string
    {
        asort($weights);

        return (string)array_key_first($weights);
    }
}
