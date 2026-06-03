<?php

namespace App\Services\SnipeIt;

readonly class SnipeItCreatedAsset
{
    public function __construct(
        public int $hardwareId,
        public string $assetTag,
    ) {}
}
