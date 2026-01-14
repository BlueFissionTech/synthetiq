<?php

namespace BlueFission\SynthetIQ\Clients;

interface NewsClientInterface
{
    public function getHeadlines(string $topic = '', string $location = ''): array;
}
