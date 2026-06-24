<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Clients;

class NullLocationClient implements LocationClientInterface
{
    public function __construct(protected string $location = '')
    {
    }

    public function getIpLocation(): string
    {
        return $this->location;
    }
}
