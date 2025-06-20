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
        /* ── Skip entire path if it lives inside an omitted directory ── */
        if ($this->shouldSkipPath($event->path)) {
            return;          // ignore completely
        }

        [$name, $ext] = $this->splitNameExt($event->path);

        /* ── skip unwanted file types --------------------------------------- */
        if (!$isDir && $this->shouldSkipExtension($ext)) {
            return;
        }


        File::updateOrCreate(
            ['path' => $event->path],
            [
                'name'              => $name,
                'file_type'         => $isDir ? 'directory' : 'file',
                'extension'         => $isDir ? null : ltrim($ext, '.'),
                'revision'          => $isDir ? null : $this->extractRevision($name),
                'part_name'         => $isDir ? null : $this->extractPartName($name),
                'core_name'         => $isDir ? null : $this->extractCoreName($name),
                'product_main_type' => $this->segment($event->path, 0),
                'product_sub_type'  => $this->segment($event->path, 1, true),
                'parent'            => $this->parentFolder($event->path),
                'parent_path'       => $this->parentPath($event->path),
                'depth'             => substr_count($event->path, '/'),
                'origin'            => $event->origin,
                'content_hash'      => $event->hash ?? null,
                'size'              => $event->size ?? null,
                'modified_at'       => $event->modifiedAt ?? now(),
            ]
        );
    }

    /* =========  Files & Folders Omitting utilities  ========= */

    /**
     * Return TRUE when the file extension is in the omit list.
     */
    private function shouldSkipExtension(string $ext): bool
    {
        $clean = strtolower(ltrim($ext, '.'));

        return in_array(
            $clean,
            array_map('strtolower', config('projectors.filesystem.omit_extensions', [])),
            true
        );
    }

    /**
     * Return TRUE when any segment of the given path
     *  • equals an “omit_directories” entry, OR
     *  • starts with one of the “omit_directory_prefixes”.
     */
    private function shouldSkipPath(string $path): bool
    {
        $segments = explode('/', $path);

        $omit   = array_map('strtolower', config('projectors.filesystem.omit_directories', []));
        $prefix = array_map('strtolower', config('projectors.filesystem.omit_directory_prefixes', []));

        foreach ($segments as $seg) {
            $seg = strtolower($seg);

            // exact directory names
            if (in_array($seg, $omit, true)) {
                return true;
            }

            // prefixes (e.g. everything that starts with '00')
            foreach ($prefix as $p) {
                if ($p !== '' && str_starts_with($seg, $p)) {
                    return true;
                }
            }
        }
        return false;
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
     * Turn something like “SUB-TYPE-1_ PART TWO _revA.pdf” into “PART TWO”.
     */
    private function extractCoreName(string $fileName): ?string
    {
        // Extract the part before revision or file extension
        $part = $this->extractPartName($fileName); // e.g. "SUB-TYPE-1_ PART TWO"

        if ($part === null) {
            return null;
        }

        // Find the first underscore and take everything after it
        $pos = strpos($part, '_');
        if ($pos === false) {
            return trim($part);  // Return the whole part if no underscore is found
        }

        // Trim leading/trailing spaces and return the core name
        return trim(substr($part, $pos + 1)); // Keep the internal spaces intact
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

    /**
     * Return the immediate parent directory name or NULL when the file/folder
     * is stored at the repository root.
     *
     *  examples ────────────────────────────────────────────────────────────────
     *   "A/B/C.txt"          → "B"
     *   "A/B"   (directory)  → "A"
     *   "rootFile.txt"       → null
     */
    private function parentFolder(string $path): ?string
    {
        if (!str_contains($path, '/')) {
            return null;                 // file/dir lives at repo root
        }

        return basename(dirname($path)); // "B"
    }

    /** Full path to the parent folder (“A/B” in “A/B/C.txt”) or NULL at root. */
    private function parentPath(string $path): ?string
    {
        if (!str_contains($path, '/')) {
            return null;
        }

        $parent = dirname($path);        // "A/B"
        return $parent === '.' ? null : $parent;
    }
}
