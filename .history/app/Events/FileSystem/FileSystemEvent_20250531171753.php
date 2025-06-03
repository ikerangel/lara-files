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
        $data = [
            'path' => $this->path,
            'origin' => $this->origin,
            'hash' => $this->hash,
            'modified_at' => $this->modifiedAt?->format('Y-m-d H:i:s'),
            'size' => $this->size,
            'type' => $this->getEventType(),
        ];

        return $data;
    }

    private function getEventType(): string
    {
        $className = get_class($this);

        if (str_contains($className, 'Directory')) {
            return 'directory';
        }

        if (str_contains($className, 'File')) {
            return 'file';
        }

        return 'unknown';
    }
}
