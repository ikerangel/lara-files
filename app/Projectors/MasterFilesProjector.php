<?php

namespace App\Projectors;

use App\Events\FileSystem\{
    DirectoryDeleted,           // we only need delete events for directories
    FileCreated, FileModified,
    FileDeleted,
    FileSystemEvent
};
use App\Models\File;            // read-only source
use App\Models\Master;          // write model
use Illuminate\Support\Facades\Config;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class MasterFilesProjector extends Projector
{
    /* =========  Projector Order  ========= */
    public int $weight = 2; //Projectors with a lower weight run first

    /* =========  Event hooks  ========= */

    public function onFileCreated(FileCreated $event): void
    {
        // early-out when path lives in an omitted directory
        if ($this->shouldSkipPath($event->path)) {
          return;
        }

        $this->refreshForPath($event->path);
    }

    public function onFileModified(FileModified $event): void
    {
        // early-out when path lives in an omitted directory
        if ($this->shouldSkipPath($event->path)) {
          return;
        }

        $this->refreshForPath($event->path);
    }

    public function onFileDeleted(FileDeleted $event): void
    {
        // remove a vanished master **or** a vanished slave
        Master::where('path',  $event->path)
              ->orWhere('slave_path', $event->path)
              ->delete();

        // a slave deletion may invalidate other masters
        $this->refreshNeighbouringMasters($event->path);
    }

    public function onDirectoryDeleted(DirectoryDeleted $event): void
    {
        // drop everything inside the deleted tree
        Master::where('path',       'like', $event->path.'%')
              ->orWhere('slave_path','like', $event->path.'%')
              ->delete();
    }

    /* =========  Core logic  ========= */

    private function refreshForPath(string $path): void
    {
        $file = File::where('path', $path)->first();
        if (!$file) {
            return; // race-condition guard – FileSystemProjector not committed yet
        }

        if ($this->isMasterExt($file->extension)) {
            $this->evaluateMaster($file);          // (re-)create or delete
        }

        if ($this->isSlaveExt($file->extension)) {
            // a new / changed slave may create new masters in the folder
            $candidates = File::query()
                ->where('parent_path', $file->parent_path)
                ->whereIn('extension', Config::get('projectors.masterfiles.master_extensions', []))
                ->where('part_name',   $file->part_name)
                ->get();

            foreach ($candidates as $cand) {
                $this->evaluateMaster($cand);
            }
        }
    }

    /**
     * Check whether the given File *qualifies* as a master
     * (i.e. has an accompanying slave in the same folder) and
     * update the `masters` table accordingly.
     */
    private function evaluateMaster(File $master): void
    {
        $slave = $this->locateSlave($master);

        if ($slave) {
            Master::updateOrCreate(
                ['path' => $master->path],
                [
                    'revision'     => $master->revision,
                    'parent_path'  => $master->parent_path,
                    'content_hash' => $master->content_hash,
                    'slave_path'   => $slave->path,
                    'modified_at'  => $master->modified_at,
                ],
            );
        } else {
            // No slave any more → not a master
            Master::where('path', $master->path)->delete();
        }
    }

    /** Search for a slave (.pdf, …) that lives in the same folder and
     *  shares the same *part_name*.
     */
    private function locateSlave(File $master): ?File
    {
        return File::query()
            ->where('parent_path', $master->parent_path)
            ->where('part_name',   $master->part_name)
            ->whereIn('extension', Config::get('projectors.masterfiles.slave_extensions', []))
            ->orderByDesc('revision')        // newest first; keeps rule flexible
            ->first();
    }

    private function refreshNeighbouringMasters(string $deletedPath): void
    {
        $dir = str_contains($deletedPath, '/') ? dirname($deletedPath) : null;
        if (!$dir) {
            return;
        }

        // grab every candidate master in that folder and re-evaluate
        $candidates = File::query()
            ->where('parent_path', $dir)
            ->whereIn('extension', Config::get('projectors.masterfiles.master_extensions', []))
            ->get();

        foreach ($candidates as $cand) {
            $this->evaluateMaster($cand);
        }
    }

    /* =========  Helpers  ========= */

    private function isMasterExt(?string $ext): bool
    {
        return in_array(strtolower($ext ?? ''), Config::get('projectors.masterfiles.master_extensions', []), true);
    }

    private function isSlaveExt(?string $ext): bool
    {
        return in_array(strtolower($ext ?? ''), Config::get('projectors.masterfiles.slave_extensions', []), true);
    }
    /* =========  Path-omitting helper  ========= */

    private function shouldSkipPath(string $path): bool
    {
        $segments = explode('/', $path);

        $omit   = array_map('strtolower',
            Config::get('projectors.masterfiles.omit_directories', [])
        );
        $prefix = array_map('strtolower',
            Config::get('projectors.masterfiles.omit_directory_prefixes', [])
        );

        foreach ($segments as $seg) {
            $seg = strtolower($seg);

            if (in_array($seg, $omit, true)) {
                return true;                       // full match
            }

            foreach ($prefix as $p) {              // prefix match
                if ($p !== '' && str_starts_with($seg, $p)) {
                    return true;
                }
            }
        }
        return false;
    }
}
