<?php

namespace App\Projectors;

use App\Events\FileSystem\{
    FileCreated, FileModified, FileDeleted,
    DirectoryDeleted
};
use App\Models\File;   // read-only
use App\Models\Master; // read-only
use App\Models\Part;   // write
use Illuminate\Support\Facades\Config;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PartsProjector extends Projector
{
    /* run after MasterFilesProjector (weight = 2) */
    public int $weight = 3;

    /* =====  Event hooks  ====================================== */

    public function onFileCreated(FileCreated $event): void
    {
        $this->refreshForPath($event->path);
    }

    public function onFileModified(FileModified $event): void
    {
        $this->refreshForPath($event->path);
    }

    public function onFileDeleted(FileDeleted $event): void
    {
        Part::where('path', $event->path)->delete();
    }

    public function onDirectoryDeleted(DirectoryDeleted $event): void
    {
        Part::where('path', 'like', $event->path.'%')->delete();
    }

    /* =====  Core  ============================================= */

    private function refreshForPath(string $path): void
    {
        if ($this->shouldSkipPath($path)) {
            Part::where('path', $path)->delete(); // clean up if previously stored
            return;
        }

        $file = File::where('path', $path)->first();
        if (!$file) {
            return; // FileSystemProjector not yet committed
        }

        if (!$this->isPartExt($file->extension)) {
            return; // not an interesting file type
        }

        /* ── locate a potential master ───────────────────────── */
        $master = Master::query()
            ->where(function ($q) use ($file) {
                $q->where('part_name', $file->part_name)
                  ->orWhere('content_hash', $file->content_hash);
            })
            ->orderByDesc('slave_revision')   // newest pdf first
            ->first();

        $contentMatches = $master && $file->content_hash
            && $file->content_hash === $master->content_hash;

        Part::updateOrCreate(
            ['path' => $file->path],
            [
                'part_name'         => $file->part_name,
                'parent_path'       => $file->parent_path,
                'extension'         => $file->extension,
                'master_revision'   => $master?->master_revision,
                'slave_path'        => $master?->slave_path,
                'slave_revision'    => $master?->slave_revision,
                'content_hash'      => $file->content_hash,
                'content_as_master' => $contentMatches,
                'modified_at'       => $file->modified_at,
            ]
        );
    }

    /* =====  Helpers  ========================================== */

    private function isPartExt(?string $ext): bool
    {
        return in_array(strtolower($ext ?? ''), Config::get('projectors.parts.part_extensions', []), true);
    }

    private function shouldSkipPath(string $path): bool
    {
        $segments = explode('/', $path);

        $omit   = array_map('strtolower', Config::get('projectors.parts.omit_directories', []));
        $prefix = array_map('strtolower', Config::get('projectors.parts.omit_directory_prefixes', []));

        foreach ($segments as $seg) {
            $seg = strtolower($seg);

            if (in_array($seg, $omit, true)) {
                return true;
            }

            foreach ($prefix as $p) {
                if ($p !== '' && str_starts_with($seg, $p)) {
                    return true;
                }
            }
        }
        return false;
    }
}
