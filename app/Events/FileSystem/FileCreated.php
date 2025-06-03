<?php

namespace App\Events\FileSystem;

class FileCreated extends FileSystemEvent
{
    public function __construct(
        string $path,
        string $origin = 'real-time',
        ?string $hash = null,
        ?\DateTime $modifiedAt = null,
        ?int $size = null
    ) {
        parent::__construct($path, $origin, $hash, $modifiedAt, $size);
    }
}
