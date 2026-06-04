<?php

namespace App\Services\SnipeIt;

readonly class SnipeItCreatedRecord
{
    public function __construct(
        public string $type,
        public int $id,
        public ?string $assetTag = null,
        public ?string $name = null,
    ) {}

    public function isHardware(): bool
    {
        return $this->type === 'hardware';
    }

    public function isAccessory(): bool
    {
        return $this->type === 'accessory';
    }

    public function summaryLabel(): string
    {
        if ($this->isHardware() && $this->assetTag) {
            return 'tag: '.$this->assetTag.', ID: '.$this->id;
        }

        return ($this->name ?: 'Accessory').' (ID: '.$this->id.')';
    }
}
