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

  /**
   * Return the revision (e.g. "revA", "v1", "20250617") or NULL when the tail
   * does not match one of the allowed patterns[1].
   *
   * ── How the unified regular expression works ─────────────────────────────
   *   1.  rev[0-9a-z]+   → matches strings that start with “rev” followed by
   *       one or more digits or lowercase letters, e.g. “revA”, “rev01”[1].
   *   2.  v[0-9]+[a-z]*  → matches strings that start with “v” (upper- or
   *       lower-case) followed by one or more digits and *optionally* a
   *       trailing letter sequence, e.g. “v1”, “V1”, “v2b”, “v10a”[2].
   *   3.  [0-9]{8}       → matches exactly eight consecutive digits, which we
   *       use for dates like “20250617” (YYYYMMDD)[3].
   *   4.  The /i modifier makes the whole pattern case-insensitive, so
   *       “_V1” and “_v1” are treated the same[4].
   */
  private function extractRevision(string $name): ?string
  {
      $pos = strrpos($name, '_');          // last underscore[1]
      if ($pos === false) {                // none => no revision[1]
          return null;                     // early exit[1]
      }

      $token = substr($name, $pos + 1);    // candidate after "_"[2]

      // Allowed patterns combined into one regular expression[2]
      $isRevision = preg_match(
          '/^(?:rev[0-9a-z]+|v[0-9]+[a-z]*|[0-9]{8})$/i',  // unified regex[2]
          $token
      );

      return $isRevision ? $token : null;  // keep or ignore[2]
  }

    /**
     * Strip “_revision” only when one was actually detected[1].
     */
    private function extractPartName(string $name): string
    {
        $rev = $this->extractRevision($name);        // reuse logic[2]
        return $rev !== null
            ? substr($name, 0, -strlen('_' . $rev))  // cut off tail[1]
            : $name;                                 // leave intact[1]
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
