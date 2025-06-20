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
        $this->dispatchRefreshes($event->path);
    }

    public function onFileModified(FileModified $event): void
    {
        $this->dispatchRefreshes($event->path);
    }

    public function onFileDeleted(FileDeleted $event): void
    {
        /* 1. remove the row when the deleted file *was* a part          */
        Part::where('path', $event->path)->delete();

        /* 2. if a slave disappeared, the related parts lose their link  */
        $file = File::where('path', $event->path)->first();
        if ($file && $this->isSlaveExt($file->extension)) {
            $this->refreshByPartName($file->part_name);
        }
    }

    public function onDirectoryDeleted(DirectoryDeleted $event): void
    {
        Part::where('path', 'like', $event->path.'%')->delete();
    }

    /* ───── Dispatcher ─────────────────────────────────────────── */

    /**
     * Decide whether the incoming path is
     *   • a “part”  → refresh itself
     *   • a “slave” → refresh every sibling part that shares the part_name
     */
    private function dispatchRefreshes(string $path): void
    {
        if ($this->shouldSkipPath($path)) {
            return;
        }

        $file = File::where('path', $path)->first();
        if (!$file) {
            return; // FileSystemProjector not committed yet
        }

        if ($this->isPartExt($file->extension)) {
            $this->refreshForPath($file->path);
        }

        if ($this->isSlaveExt($file->extension)) {
            $this->refreshByPartName($file->part_name);
        }
    }

    private function refreshByPartName(string $partName): void
    {
        Part::where('part_name', $partName)
            ->pluck('path')
            ->each(fn (string $p) => $this->refreshForPath($p));
    }

    /* =====  Core  ============================================= */

    private function refreshForPath(string $path): void
    {
        $file = File::where('path', $path)->first();
        if (!$file || !$this->isPartExt($file->extension)) {
            return;
        }

        /* Find a candidate master by name OR identical hash */
        $master = Master::query()
            ->where(function ($q) use ($file) {
                $q->where('part_name', $file->part_name)
                  ->orWhere('content_hash', $file->content_hash);
            })
            ->orderByDesc('slave_revision')   // newest slave first
            ->first();

        $contentMatches = $master
            && $file->content_hash
            && $file->content_hash === $master->content_hash;

        Part::updateOrCreate(
            ['path' => $file->path],
            [
                'part_name'         => $file->part_name,
                'core_name'         => $file->core_name,
                'parent_path'       => $file->parent_path,
                'parent'            => $file->parent,
                'extension'         => $file->extension,
                'master_path'       => $master?->path,
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
        return in_array(
            strtolower($ext ?? ''),
            Config::get('projectors.parts.part_extensions', []),
            true
        ); // “par, asm, doc/x, xls/x” by default[1]
    }

    private function isSlaveExt(?string $ext): bool
    {
        return in_array(
            strtolower($ext ?? ''),
            Config::get('projectors.masterfiles.slave_extensions', []),
            true
        ); // currently only “pdf”[1]
    }

    /**
     * TRUE when any path segment
     *   • matches an entry in   omit_directories, OR
     *   • starts  with a prefix omit_directory_prefixes
     */
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
