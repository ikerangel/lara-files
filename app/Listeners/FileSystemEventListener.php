<?php

namespace App\Listeners;

use App\Events\FileSystem\FileSystemEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class FileSystemEventListener implements ShouldQueue
{
    public function handle($event)
    {
        if (!$event instanceof FileSystemEvent) {
            return;
        }

        // Enhance event properties with additional metadata
        $event->addMetadata([
            'file_type' => $this->determineFileType($event),
            'event_type' => class_basename($event),
            'origin' => $event->origin,
        ]);
    }

    private function determineFileType(FileSystemEvent $event): string
    {
        $eventClass = class_basename($event);

        if (str_contains($eventClass, 'Directory')) {
            return 'directory';
        }

        if (str_contains($eventClass, 'File')) {
            return 'file';
        }

        return 'unknown';
    }
}
