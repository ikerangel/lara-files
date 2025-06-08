<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Spatie\EventSourcing\Models\StoredEvent;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class MonitorStoredEvents extends Command
{
    protected $signature = 'events:monitor-db
                            {--delay=1 : Seconds to wait after detecting an event}';

    protected $description = 'Monitor stored events table for real-time persistence verification';
    private $shouldExit = false; // Flag to control exit
    public function handle()
    {
        // Setup signal handler for graceful exit
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            $this->shouldExit = true;
        });

        $lastId = EloquentStoredEvent::max('id') ?? 0;
        $delay = max(0.5, (float)$this->option('delay'));  // Minimum 0.5s delay

        $this->info("Monitoring stored_events table. Press Ctrl+C to exit.");
        $this->line("Initial last event ID: $lastId");
        $this->line("Delay after event: {$delay}s");
        $this->line(str_repeat('-', 60));

        $startTime = microtime(true);
        $eventCount = 0;

        while (!$this->shouldExit) {
            $events = EloquentStoredEvent::where('id', '>', $lastId)
                ->orderBy('id')
                ->get();

            if ($events->isNotEmpty()) {
                foreach ($events as $event) {
                    if ($this->shouldExit) break 2; // Exit outer loop immediately

                    $this->displayEvent($event);
                    $lastId = $event->id;
                    $eventCount++;

                    // Add delay after each event
                    usleep($delay * 500000);
                }

                // Add batch delay
                usleep($delay * 500000);

                $this->printStats($startTime, $eventCount);
            } else {
                // Check for exit signal during idle
                if ($this->shouldExit) break;
                usleep(500000);
            }
        }

        $this->newLine();
        $this->info('Monitoring stopped.');
    }

    protected function displayEvent(EloquentStoredEvent $event)
    {
        $properties = json_decode($event->event_properties, true);
        $path = $properties['path'] ?? '';

        // Normalize and decode path
        $path = str_replace('\\\\', '/', $path);
        $path = str_replace(
            ['\u00bd', '\u00bc', '\u00be'],
            ['½', '¼', '¾'],
            $path
        );

        // Determine event type
        $eventType = $this->getEventType($event->event_class);

        // Calculate storage delay
        $storageDelay = number_format(
            Carbon::now()->diffInMilliseconds($event->created_at) / 1000,
            3
        );

        $this->line("<fg=yellow>╔═[NEW EVENT]═[ID: {$event->id}]═[+{$storageDelay}s]═╗</>");
        $this->line("<fg=cyan>║ Type:</> " . Str::padRight($eventType, 52) . "║");
        $this->line("<fg=cyan>║ Path:</> " . Str::limit($path, 52) . " ║");
        $this->line("<fg=cyan>║ Stored At:</> {$event->created_at}" . str_repeat(' ', 30) . "║");
        $this->line("<fg=yellow>╚═══════════════════════════════════════════════════════╝</>");
    }

    protected function getEventType(string $className): string
    {
        return match (true) {
            Str::contains($className, 'FileCreated') => '📄 FILE CREATED',
            Str::contains($className, 'FileDeleted') => '❌ FILE DELETED',
            Str::contains($className, 'FileModified') => '🔄 FILE MODIFIED',
            Str::contains($className, 'DirectoryCreated') => '📁 DIRECTORY CREATED',
            Str::contains($className, 'DirectoryDeleted') => '🗑️ DIRECTORY DELETED',
            default => '❓ ' . class_basename($className)
        };
    }

    protected function printStats(float $startTime, int $eventCount)
    {
        $elapsed = microtime(true) - $startTime;
        $rate = $eventCount > 0 ? number_format($elapsed / $eventCount, 3) : 0;

        $this->line("\n<fg=magenta>[STATS] Events:</> $eventCount"
            . " <fg=magenta>Elapsed:</> " . number_format($elapsed, 1) . "s"
            . " <fg=magenta>Avg:</> {$rate}s/event\n");
    }

}
