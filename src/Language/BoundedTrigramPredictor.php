<?php

namespace BlueFission\SynthetIQ\Language;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;

class BoundedTrigramPredictor
{
    protected array $_states = [];
    protected array $_beginnings = [];
    protected int $_maxStates;
    protected int $_maxBeginnings;
    protected int $_maxTransitions;

    public function __construct(array $options = [])
    {
        $this->_maxStates = (int)Num::max(1, (int)($options['max_states'] ?? 10000));
        $this->_maxBeginnings = (int)Num::max(1, (int)($options['max_beginnings'] ?? 1000));
        $this->_maxTransitions = (int)Num::max(1, (int)($options['max_transitions'] ?? 100));
    }

    public function addSentence(string $sentence): void
    {
        $words = $this->tokenize($sentence);
        if (Arr::count($words) < 3) {
            return;
        }

        $this->rememberBeginning($words[0] . ' ' . $words[1]);

        $count = Arr::count($words);
        for ($i = 2; $i < $count; $i++) {
            $trigram = $words[$i - 2] . ' ' . $words[$i - 1];
            $nextWord = $words[$i];

            if (!Arr::hasKey($this->_states, $trigram) && Arr::count($this->_states) >= $this->_maxStates) {
                $oldestState = Arr::keys($this->_states)[0] ?? null;
                if (Val::is($oldestState)) {
                    unset($this->_states[$oldestState]);
                }
            }

            if (!Arr::hasKey($this->_states, $trigram)) {
                $this->_states[$trigram] = [];
            }

            if (!Arr::hasKey($this->_states[$trigram], $nextWord) && Arr::count($this->_states[$trigram]) >= $this->_maxTransitions) {
                $lowest = $this->lowestWeightedKey($this->_states[$trigram]);
                unset($this->_states[$trigram][$lowest]);
            }

            $this->_states[$trigram][$nextWord] = ($this->_states[$trigram][$nextWord] ?? 0) + 1;
        }
    }

    public function predictNextWord(string $sentence): ?string
    {
        $words = $this->tokenize($sentence);
        $previousTwoWords = implode(' ', Arr::slice($words, -2));
        if (Val::isEmpty($previousTwoWords) || Val::isEmpty($this->_states[$previousTwoWords] ?? null)) {
            return null;
        }

        return $this->weightedPick($this->_states[$previousTwoWords]);
    }

    public function predictNextWords(string $sentence, int $limit = 5): array
    {
        $words = $this->tokenize($sentence);
        $previousTwoWords = implode(' ', Arr::slice($words, -2));
        if (Val::isEmpty($previousTwoWords) || Val::isEmpty($this->_states[$previousTwoWords] ?? null)) {
            return [];
        }

        $candidates = $this->_states[$previousTwoWords];
        arsort($candidates);

        return Arr::slice(Arr::keys($candidates), 0, (int)Num::max(1, $limit));
    }

    public function tokenize(string $sentence): array
    {
        $tokens = preg_split('/\s+/', Str::lower(Str::trim($sentence)), -1, PREG_SPLIT_NO_EMPTY);

        return Arr::is($tokens) ? $tokens : [];
    }

    protected function rememberBeginning(string $beginning): void
    {
        if (!Arr::hasKey($this->_beginnings, $beginning) && Arr::count($this->_beginnings) >= $this->_maxBeginnings) {
            $oldestBeginning = Arr::keys($this->_beginnings)[0] ?? null;
            if (Val::is($oldestBeginning)) {
                unset($this->_beginnings[$oldestBeginning]);
            }
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

        $keys = Arr::keys($weights);

        return (string)($keys[0] ?? '');
    }

    protected function lowestWeightedKey(array $weights): string
    {
        asort($weights);

        $keys = Arr::keys($weights);

        return (string)($keys[0] ?? '');
    }
}
