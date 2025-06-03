<?php

namespace App\Events\FileSystem;

class FileModified extends FileSystemEvent
{
    public ?string $previousHash;

    public function __construct(
        string $path,
        string $origin = 'real-time',
        ?string $hash = null,
        ?\DateTime $modifiedAt = null,
        ?int $size = null,
        ?string $previousHash = null
    ) {
        parent::__construct($path, $origin, $hash, $modifiedAt, $size);
        $this->previousHash = $previousHash;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'previous_hash' => $this->previousHash,
        ]);
    }
}
