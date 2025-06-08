<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class MonitorStoredEvents extends Command
{
    protected $signature = 'events:monitor-db
                            {--delay=1 : Seconds to wait after detecting an event}';

    protected $description = 'Monitor stored events table for real-time persistence verification';

    private $shouldExit = false;

    public function handle()
    {
        // Setup signal handler for graceful exit
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn() => $this->shouldExit = true);
        }

        $lastId = EloquentStoredEvent::max('id') ?? 0;
        $delay = max(0.5, (float)$this->option('delay'));

        $this->info("Monitoring stored_events table. Press Ctrl+C to exit.");
        $this->line("Initial last event ID: $lastId");
        $this->line("Delay after event: {$delay}s");
        $this->line(str_repeat('-', 60));

        while (!$this->shouldExit) {
            $events = EloquentStoredEvent::where('id', '>', $lastId)
                ->orderBy('id')
                ->get();

            if ($events->isNotEmpty()) {
                foreach ($events as $event) {
                    if ($this->shouldExit) break 2;

                    $this->displayEvent($event);
                    $lastId = $event->id;

                    // Add delay after each event
                    usleep((int)($delay * 1000000));
                }
            } else {
                if ($this->shouldExit) break;
                usleep(500000);  // 0.5s sleep when no events
            }
        }

        $this->newLine();
        $this->info('Monitoring stopped.');
    }

    protected function displayEvent(EloquentStoredEvent $event)
    {
        $properties = $event->event_properties;
        $path = $properties['path'] ?? '';

        // Normalize Windows paths
        $path = str_replace('\\', '/', $path);

        $this->line(sprintf(
            "[%s] %s: %s",
            $event->created_at->format('H:i:s'),
            $this->getEventType($event->event_class),
            $path
        ));
    }

    protected function getEventType(string $className): string
    {
        return match (true) {
            str_contains($className, 'FileCreated') => '📄 FILE CREATED',
            str_contains($className, 'FileDeleted') => '❌ FILE DELETED',
            str_contains($className, 'FileModified') => '🔄 FILE MODIFIED',
            str_contains($className, 'DirectoryCreated') => '📁 DIRECTORY CREATED',
            str_contains($className, 'DirectoryDeleted') => '🗑️ DIRECTORY DELETED',
            default => '❓ ' . class_basename($className)
        };
    }
}
