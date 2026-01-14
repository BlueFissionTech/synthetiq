<?php

namespace BlueFission\SynthetIQ\Clients;

interface LocationClientInterface
{
    public function getIpLocation(): string;
}
