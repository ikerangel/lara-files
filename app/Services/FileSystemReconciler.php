<?php

namespace App\Services;

use SplFileInfo;
use Carbon\Carbon;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\FileSystem\FileCreated;
use App\Events\FileSystem\FileModified;
use App\Events\FileSystem\FileDeleted;
use App\Events\FileSystem\DirectoryCreated;
use App\Events\FileSystem\DirectoryDeleted;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class FileSystemReconciler
{
    private string $basePath;
    private array $discrepancies = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function execute(): array
    {
        Log::info("Starting file system reconciliation", ['path' => $this->basePath]);

        $currentState = $this->crawlFileSystem();
        $eventTimeline = $this->getEventTimeline();

        // Two-phase reconciliation
        $this->findMissingItems($currentState, $eventTimeline);  // Phase 1: Find deletions
        $this->findExistingItemDiscrepancies($currentState, $eventTimeline);  // Phase 2: Find additions/modifications

        $generatedEvents = $this->generateReconciliationEvents();

        Log::info("Reconciliation completed", [
            'items_scanned' => count($currentState),
            'discrepancies_found' => count($this->discrepancies),
            'events_generated' => $generatedEvents
        ]);

        return [
            'scanned' => count($currentState),
            'discrepancies' => count($this->discrepancies),
            'events_created' => $generatedEvents
        ];
    }

    private function crawlFileSystem(): array
    {
        $currentState = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relativePath = $this->getRelativePath($path);

            $currentState[$relativePath] = [
                'type' => $item->isDir() ? 'directory' : 'file',
                'path' => $relativePath,
                'modified_at' => Carbon::createFromTimestamp($item->getMTime()),
                'size' => $item->isFile() ? $item->getSize() : 0,
                'hash' => $item->isFile() ? $this->calculateFileHash($path) : null
            ];
        }

        return $currentState;
    }

    private function getEventTimeline(): array
    {
        $events = EloquentStoredEvent::query()
            ->where('event_class', 'LIKE', '%FileSystem%')
            ->orderBy('created_at', 'desc')
            ->get(['event_class', 'event_properties', 'created_at']);

        $timeline = [];

        foreach ($events as $event) {
            $props = $event->event_properties;
            $path = $props['path'] ?? null;

            if (!$path) continue;

            // Only keep the most recent event per path
            if (!isset($timeline[$path])) {
                $timeline[$path] = [
                    'type' => $this->getEventType($event->event_class),
                    'event_class' => $event->event_class,
                    'created_at' => $event->created_at,
                    'properties' => $props
                ];
            }
        }

        return $timeline;
    }

    /**
     * Phase 1: Find items that exist in events but are missing from filesystem
     * These should be marked as deleted
     */
    private function findMissingItems(array $currentState, array $eventTimeline): void
    {
        foreach ($eventTimeline as $path => $event) {
            // Skip if this path doesn't belong to our base path
            if (!$this->pathBelongsToBasePath($path)) {
                continue;
            }

            // Skip if the last event was already a deletion
            if ($this->isDeleteEvent($event['event_class'])) {
                continue;
            }

            // If item exists in events but not in current filesystem, it was deleted
            if (!isset($currentState[$path])) {
                $this->discrepancies[$path] = [
                    'type' => $event['type'],
                    'reason' => 'missing_from_filesystem',
                    'current' => null,
                    'event' => $event
                ];
            }
        }
    }

    /**
     * Phase 2: Find discrepancies for items that exist in filesystem
     * Original logic for additions and modifications
     */
    private function findExistingItemDiscrepancies(array $currentState, array $eventTimeline): void
    {
        foreach ($currentState as $path => $current) {
            $event = $eventTimeline[$path] ?? null;

            if (!$event) {
                // Item exists but has no recorded event
                $this->discrepancies[$path] = [
                    'type' => $current['type'],
                    'reason' => 'missing_event',
                    'current' => $current,
                    'event' => null
                ];
                continue;
            }

            if ($this->isDeleteEvent($event['event_class'])) {
                // Item exists but was deleted in event history
                $this->discrepancies[$path] = [
                    'type' => $current['type'],
                    'reason' => 'deleted_but_exists',
                    'current' => $current,
                    'event' => $event
                ];
                continue;
            }

            if ($current['type'] === 'file') {
                $eventTime = Carbon::parse($event['created_at']);
                $currentTime = $current['modified_at'];

                if ($currentTime->gt($eventTime)) {
                    // File modified after last event
                    $this->discrepancies[$path] = [
                        'type' => 'file',
                        'reason' => 'modified_after_event',
                        'current' => $current,
                        'event' => $event
                    ];
                }
            }
        }
    }

    private function generateReconciliationEvents(): int
    {
        $count = 0;

        // Sort discrepancies to handle deletions of parent folders before children
        $sortedDiscrepancies = $this->sortDiscrepanciesForDeletion($this->discrepancies);

        foreach ($sortedDiscrepancies as $path => $discrepancy) {
            try {
                switch ($discrepancy['reason']) {
                    case 'missing_from_filesystem':
                        // Create deletion events for items that no longer exist
                        if ($discrepancy['type'] === 'directory') {
                            event(new DirectoryDeleted($path, 'reconciled'));
                            $count++;
                        } else {
                            event(new FileDeleted($path, 'reconciled'));
                            $count++;
                        }
                        break;

                    case 'missing_event':
                    case 'deleted_but_exists':
                        if ($discrepancy['type'] === 'directory') {
                            event(new DirectoryCreated($path, 'reconciled'));
                            $count++;
                        } else {
                            event(new FileCreated(
                                $path,
                                'reconciled',
                                $discrepancy['current']['hash'],
                                $discrepancy['current']['modified_at'],
                                $discrepancy['current']['size']
                            ));
                            $count++;
                        }
                        break;

                    case 'modified_after_event':
                        event(new FileModified(
                            $path,
                            'reconciled',
                            $discrepancy['current']['hash'],
                            $discrepancy['current']['modified_at'],
                            $discrepancy['current']['size'],
                            $discrepancy['event']['properties']['hash'] ?? null
                        ));
                        $count++;
                        break;
                }

                Log::debug("Generated reconciliation event", [
                    'path' => $path,
                    'reason' => $discrepancy['reason'],
                    'type' => $discrepancy['type']
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to generate reconciliation event", [
                    'path' => $path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    /**
     * Sort discrepancies to handle directory deletions properly
     * Directories should be deleted after their contents (deepest first)
     */
    private function sortDiscrepanciesForDeletion(array $discrepancies): array
    {
        $deletions = [];
        $others = [];

        foreach ($discrepancies as $path => $discrepancy) {
            if ($discrepancy['reason'] === 'missing_from_filesystem') {
                $deletions[$path] = $discrepancy;
            } else {
                $others[$path] = $discrepancy;
            }
        }

        // Sort deletions by path depth (deepest first) to delete children before parents
        uksort($deletions, function ($a, $b) {
            $depthA = substr_count($a, DIRECTORY_SEPARATOR);
            $depthB = substr_count($b, DIRECTORY_SEPARATOR);

            if ($depthA === $depthB) {
                return strcmp($b, $a); // Reverse alphabetical for same depth
            }

            return $depthB - $depthA; // Deeper paths first
        });

        return array_merge($deletions, $others);
    }

    /**
     * Check if a path belongs to our base path
     */
    private function pathBelongsToBasePath(string $path): bool
    {
        // For deleted items, we can't use realpath since the path doesn't exist
        // Instead, do a simple string comparison
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Check if it's a relative path within our base path structure
        // Paths should not start with / or contain ..
        return !str_starts_with($normalizedPath, DIRECTORY_SEPARATOR) &&
              !str_contains($normalizedPath, '..');
    }

    private function getRelativePath(string $absolutePath): string
    {
        return str_replace($this->basePath . DIRECTORY_SEPARATOR, '', $absolutePath);
    }

    private function calculateFileHash(string $path): ?string
    {
        if (!is_file($path)) return null;

        try {
            return hash_file('sha256', $path);
        } catch (\Exception $e) {
            Log::warning("Could not calculate file hash", ['path' => $path]);
            return null;
        }
    }

    private function getEventType(string $className): string
    {
        if (str_contains($className, 'Directory')) return 'directory';
        if (str_contains($className, 'File')) return 'file';
        return 'unknown';
    }

    private function isDeleteEvent(string $className): bool
    {
        return str_contains($className, 'Deleted');
    }
}
