<?php

namespace App\Projectors;

use App\Events\FileSystem\{
    DirectoryCreated, DirectoryDeleted,
    FileCreated, FileDeleted, FileModified,
    FileSystemEvent
};
use App\Models\File;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class FileSystemProjector extends Projector
{
    /* =========  Event hooks  ========= */

    public function onFileCreated(FileCreated $event): void
    {
        $this->update($event);
    }

    public function onDirectoryCreated(DirectoryCreated $event): void
    {
        $this->update($event, true);
    }

    public function onFileModified(FileModified $event): void
    {
        $this->update($event);               // just overwrite hash / size / modified_at
    }

    public function onFileDeleted(FileDeleted $event): void
    {
        File::where('path', $event->path)->delete();
    }

    public function onDirectoryDeleted(DirectoryDeleted $event): void
    {
        // delete directory itself *and* everything below it
        File::where('path', 'like', $event->path.'%')->delete();
    }

    /* =========  Helpers  ========= */

    private function update(FileSystemEvent $event, bool $isDir = false): void
    {
        [$name, $ext] = $this->splitNameExt($event->path);

        File::updateOrCreate(
            ['path' => $event->path],
            [
                'name'              => $name,
                'file_type'         => $isDir ? 'directory' : 'file',
                'extension'         => $isDir ? null : ltrim($ext, '.'),
                'revision'          => $isDir ? null : $this->extractRevision($name),
                'part_name'         => $isDir ? null : $this->extractPartName($name),
                'product_main_type' => $this->segment($event->path, 0),
                'product_sub_type'  => $this->segment($event->path, 1, true),
                'parent'            => $this->parentPath($event->path),
                'depth'             => substr_count($event->path, '/'),
                'origin'            => $event->origin,
                'content_hash'      => $event->hash ?? null,
                'size'              => $event->size ?? null,
                'modified_at'       => $event->modifiedAt ?? now(),
            ]
        );
    }

    /* =========  Parsing utilities  ========= */

    private function splitNameExt(string $path): array
    {
        $basename = basename($path);
        $pos = strrpos($basename, '.');
        return $pos === false
            ? [$basename, '']
            : [substr($basename, 0, $pos), substr($basename, $pos)];
    }

    private function extractRevision(string $name): ?string
    {
        if (!str_contains($name, '_')) return null;
        return substr($name, strrpos($name, '_') + 1);
    }

    private function extractPartName(string $name): ?string
    {
        $rev = $this->extractRevision($name);
        return $rev ? substr($name, 0, -strlen('_'.$rev)) : $name;
    }

    /**
     * segment(…, 0) == MAIN-TYPE-N
     * segment(…, 1) == first subtype, segment(…, 2) the next …
     * If $concat = true, concatenate all subtypes with “/”.
     */
    private function segment(string $path, int $index, bool $concat = false): ?string
    {
        $segments = explode('/', $path);       // already relative
        if ($index === 0) {
            return $segments[0] ?? null;
        }

        $subs = array_slice($segments, 1, $concat ? null : 1);
        return $subs ? ($concat ? implode('/', $subs) : $subs[0]) : null;
    }

    private function parentPath(string $path): ?string
    {
        return str_contains($path, '/')
            ? dirname($path)                     // returns "." for first level, handle…
            : null;
    }
}
