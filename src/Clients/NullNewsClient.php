<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Clients;

class NullNewsClient implements NewsClientInterface
{
    public function getHeadlines(string $topic = '', string $location = ''): array
    {
        return [];
    }
}
