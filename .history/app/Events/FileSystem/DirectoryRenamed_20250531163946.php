<?php

namespace App\Events\FileSystem;

class DirectoryRenamed extends FileSystemEvent
{
    public string $oldPath;

    public function __construct(string $oldPath, string $newPath, string $origin = 'real-time')
    {
        parent::__construct($newPath, $origin);
        $this->oldPath = $oldPath;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'old_path' => $this->oldPath,
        ]);
    }
}
