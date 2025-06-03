<?php

namespace App\Events\FileSystem;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

abstract class FileSystemEvent extends ShouldBeStored
{
    public string $path;
    public string $origin; // 'initial', 'real-time', 'reconciled'
    public ?string $hash;
    public ?\DateTime $modifiedAt;
    public ?int $size;

    public function __construct(
        string $path,
        string $origin = 'real-time',
        ?string $hash = null,
        ?\DateTime $modifiedAt = null,
        ?int $size = null
    ) {
        $this->path = $path;
        $this->origin = $origin;
        $this->hash = $hash;
        $this->modifiedAt = $modifiedAt;
        $this->size = $size;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'origin' => $this->origin,
            'hash' => $this->hash,
            'modified_at' => $this->modifiedAt?->format('Y-m-d H:i:s'),
            'size' => $this->size,
        ];
    }
}

// Directory Events
class DirectoryCreated extends FileSystemEvent
{
    public function __construct(string $path, string $origin = 'real-time')
    {
        parent::__construct($path, $origin);
    }
}

class DirectoryDeleted extends FileSystemEvent
{
    public function __construct(string $path, string $origin = 'real-time')
    {
        parent::__construct($path, $origin);
    }
}

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

// File Events
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

class FileDeleted extends FileSystemEvent
{
    public function __construct(string $path, string $origin = 'real-time')
    {
        parent::__construct($path, $origin);
    }
}

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
