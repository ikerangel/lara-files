<?php

namespace App\Events\FileSystem;

class FileRenamed extends FileSystemEvent
{
    public string $oldPath;

    public function __construct(
        string $oldPath,
        string $newPath,
        string $origin = 'real-time',
        ?string $hash = null,
        ?\DateTime $modifiedAt = null,
        ?int $size = null
    ) {
        parent::__construct($newPath, $origin, $hash, $modifiedAt, $size);
        $this->oldPath = $oldPath;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'old_path' => $this->oldPath,
        ]);
    }
}
