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
use App\Events\FileSystem\DirectoryCreated;
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
        $this->compareStates($currentState, $eventTimeline);

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

    private function compareStates(array $currentState, array $eventTimeline): void
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

        foreach ($this->discrepancies as $path => $discrepancy) {
            try {
                switch ($discrepancy['reason']) {
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
