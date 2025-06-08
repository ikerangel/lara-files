<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class MonitorStoredEvents extends Command
{
    protected $signature = 'events:monitor-db
                            {--delay=1 : Seconds to wait after detecting an event}
                            {--last=5 : Display last N events before monitoring}';

    protected $description = 'Monitor the stored_events table for real-time filesystem event persistence verification';

    private $shouldExit = false;

    public function __construct()
    {
        parent::__construct();

        $this->setHelp(<<<'HELP'
DESCRIPTION:
  Monitor the stored_events table to verify real-time persistence of filesystem events.
  Displays new events as they occur in the database with color-coded event types.

  Features:
  - Shows historical events before starting live monitoring
  - Color-coded event types for quick identification:
    • Green: File/Directory creation
    • Blue: File modification
    • Red: File/Directory deletion
  - Normalizes Windows paths to Unix-style
  - Graceful exit with Ctrl+C

USAGE:
  php artisan events:monitor-db [options]

OPTIONS:
  --delay=<seconds>   Delay between processing events (minimum 0.5s) [default: 1s]
  --last=<number>     Show last N historical events before monitoring [default: 5]

EXAMPLES:
  Start monitoring with default settings:
    php artisan events:monitor-db

  Monitor with custom settings (show last 3 events, 0.5s delay):
    php artisan events:monitor-db --last=3 --delay=0.5

OUTPUT FORMAT:
  [LIVE] [HH:MM:SS] <ICON> <COLORED_EVENT_TYPE>: <PATH>
  [HIST] [HH:MM:SS] <ICON> <COLORED_EVENT_TYPE>: <PATH>

  Prefixes:
    [LIVE] - Real-time events detected during monitoring
    [HIST] - Historical events shown at startup

  Icons:
    📄 - File event
    📁 - Directory created
    🗑️ - Directory deleted
    🔄 - File modified
    ❌ - File deleted
    ❓ - Unknown event type

COLOR SCHEME:
  \e[32mGreen\e[0m  - File/Directory creation
  \e[34mBlue\e[0m   - File modification
  \e[31mRed\e[0m    - File/Directory deletion
HELP
        );
    }

    public function handle()
    {
        // Setup signal handler for graceful exit
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn() => $this->shouldExit = true);
        }

        $lastId = EloquentStoredEvent::max('id') ?? 0;
        $delay = max(0.5, (float)$this->option('delay'));
        $showLast = max(0, (int)$this->option('last'));

        $this->info("Monitoring stored_events table. Press Ctrl+C to exit.");
        $this->line("Initial last event ID: $lastId");
        $this->line("Delay after event: {$delay}s");
        $this->line("Displaying last {$showLast} events");
        $this->line(str_repeat('-', 60));

        // Display recent events if requested
        if ($showLast > 0) {
            $this->displayRecentEvents($showLast);
            $this->line(str_repeat('-', 60));
        }

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

    protected function displayRecentEvents(int $count)
    {
        $events = EloquentStoredEvent::orderBy('id', 'desc')
            ->take($count)
            ->get()
            ->reverse();

        if ($events->isEmpty()) {
            $this->line('No historical events found');
            return;
        }

        $this->line("=== LAST {$count} EVENTS ===");
        foreach ($events as $event) {
            $this->displayEvent($event, true);
        }
    }

    protected function displayEvent(EloquentStoredEvent $event, bool $isHistorical = false)
    {
        $properties = $event->event_properties;
        $path = $properties['path'] ?? '';

        // Normalize Windows paths
        $path = str_replace('\\', '/', $path);

        // Extract time portion from the datetime string
        $time = substr($event->created_at, 11, 8);

        $prefix = $isHistorical ? '[HIST] ' : '[LIVE] ';

        $this->line(sprintf(
            "{$prefix}[%s] %s: %s",
            $time,
            $this->getColoredEventType($event->event_class),
            $path
        ));
    }

    protected function getColoredEventType(string $className): string
    {
        $type = $this->getEventType($className);

        // Apply colors based on event type
        if (str_contains($type, 'CREATED')) {
            return "<fg=green>$type</>";
        } elseif (str_contains($type, 'MODIFIED')) {
            return "<fg=blue>$type</>";
        } elseif (str_contains($type, 'DELETED')) {
            return "<fg=red>$type</>";
        }

        return $type;
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
