<?php

namespace App\Listeners;

use App\Events\FileSystem\FileSystemEvent;
use Illuminate\Support\Facades\Log;

class LogFileSystemEvent
{
    public function handle(FileSystemEvent $event)
    {
        Log::info("FileSystemEvent received", [
            'event' => get_class($event),
            'path' => $event->path,
            'origin' => $event->origin
        ]);
    }
}
