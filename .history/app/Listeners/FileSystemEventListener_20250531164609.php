<?php

namespace App\Listeners;

use App\Events\FileSystem\FileSystemEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class FileSystemEventListener implements ShouldQueue
{
    public function handle($event)
    {
        // This listener will automatically be called when FileSystemEvents are stored
        // We use it to populate our custom columns in the stored_events table

        if (!$event instanceof FileSystemEvent) {
            return;
        }

        // Find the most recently stored event for this event
        $storedEvent = DB::table('stored_events')
            ->orderBy('id', 'desc')
            ->first();

        if (!$storedEvent) {
            return;
        }

        // Determine file type
        $fileType = $this->determineFileType($event);

        // Extract file information
        $fileInfo = $this->extractFileInfo($event);

        // Update the stored event with our custom data
        DB::table('stored_events')
            ->where('id', $storedEvent->id)
            ->update([
                'origin' => $event->origin,
                'file_path' => $event->path,
                'file_hash' => $event->hash,
                'file_modified_at' => $event->modifiedAt?->format('Y-m-d H:i:s'),
                'file_size' => $event->size,
                'file_type' => $fileType,
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

    private function extractFileInfo(FileSystemEvent $event): array
    {
        return [
            'path' => $event->path,
            'hash' => $event->hash,
            'modified_at' => $event->modifiedAt,
            'size' => $event->size,
            'origin' => $event->origin,
        ];
    }
}
