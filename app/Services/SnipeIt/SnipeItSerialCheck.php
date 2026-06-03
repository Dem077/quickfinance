<?php

namespace App\Services\SnipeIt;

readonly class SnipeItSerialCheck
{
    /**
     * @param  array<int, array{id: int, asset_tag: ?string, name: ?string}>  $conflicts
     */
    public function __construct(
        public bool $isAvailable,
        public string $message,
        public array $conflicts = [],
    ) {}
}
