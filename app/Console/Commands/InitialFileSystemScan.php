<?php

namespace App\Console\Commands;

use App\Services\FileSystemScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitialFileSystemScan extends Command
{
    protected $signature = 'filesystem:scan
                          {path : The path to scan}
                          {--no-progress : Disable progress bar}';

    protected $description = 'Perform initial scan of file system and create events';

        // Constructor to set custom help message
    public function __construct()
    {
        parent::__construct();

        $this->setHelp(<<<'HELP'
          DESCRIPTION:
            Perform the initial scan of a directory and record filesystem events in the database.

            This command will:
            - Recursively scan the given directory
            - Generate events for every file and directory found
            - Store these events in the `stored_events` table
            - Display statistics and sample events at completion

            💡 Use for initial setup - not recommended for active systems.

          USAGE:
            php artisan filesystem:scan <path> [options]

          ARGUMENTS:
            path                  Absolute path to scan (required)

          OPTIONS:
            --no-progress         Disable progress bar display (useful for CI environments)

          OUTPUT:
            - Progress bar showing current file being processed (unless disabled)
            - Summary table with metrics:
                • Directories found
                • Files found
                • Total size
                • Errors encountered
                • Events created
                • Duration
                • Items per second
            - Sample of last 5 events created

          SCANNING BEHAVIOR:
            - Processes both files and directories
            - Follows symbolic links
            - Skips unreadable paths (counted as errors)
            - Records creation events for all found items

          SAMPLE OUTPUT:

            Starting initial file system scan...
            Path: /var/www

            ✅ Initial scan completed successfully!

            ┌───────────────────┬─────────────┐
            │ Metric            │ Value       │
            ├───────────────────┼─────────────┤
            │ Directories found │ 1,024       │
            │ Files found       │ 12,345      │
            │ Total size        │ 1.23 GB     │
            │ Errors            │ 3           │
            │ Events created    │ 13,369      │
            │ Duration          │ 5.2 seconds │
            │ Items per second  │ 2,571       │
            └───────────────────┴─────────────┘

            📋 Sample events created (last 5):
            ┌─────────────┬───────────────┬──────────┬─────────────────────┐
            │ Event       │ Path          │ Type     │ Created             │
            ├─────────────┼───────────────┼──────────┼─────────────────────┤
            │ FileCreated │ image.jpg     │ file     │ 2023-01-01 12:34:56 │
            │ DirCreated  │ documents     │ directory│ 2023-01-01 12:34:55 │
            └─────────────┴───────────────┴──────────┴─────────────────────┘

          EXAMPLES:
            1. Scan with progress bar:
              php artisan filesystem:scan /home/user/documents

            2. Scan without progress bar:
              php artisan filesystem:scan /mnt/data --no-progress

          NOTES:
            - Requires write permission to the database
            - Large directories may take significant time
            - Check error count for accessibility issues
          HELP
                  );
    }

    public function handle()
    {
        $path = $this->argument('path');
        $showProgress = !$this->option('no-progress');

        $this->info("Starting initial file system scan...");
        $this->info("Path: {$path}");
        $this->newLine();

        $scanner = new FileSystemScanner($path);

        $progressBar = null;
        if ($showProgress) {
            $progressBar = $this->output->createProgressBar();
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        }

        $startTime = microtime(true);
        $eventCountBefore = DB::table('stored_events')->count();

        try {
            $stats = $scanner->scan(function ($progress, $current, $total, $currentPath) use ($progressBar, $showProgress) {
                if ($showProgress && $progressBar) {
                    $progressBar->setMaxSteps($total);
                    $progressBar->setProgress($current);
                    $progressBar->setMessage(basename($currentPath));
                }
            });

            if ($showProgress && $progressBar) {
                $progressBar->finish();
                $this->newLine(2);
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $eventCountAfter = DB::table('stored_events')->count();
            $eventsCreated = $eventCountAfter - $eventCountBefore;

            // Display results
            $this->displayResults($stats, $duration, $eventsCreated);

            // Show sample events
            $this->showSampleEvents();

        } catch (\Exception $e) {
            $this->error("Scan failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayResults(array $stats, float $duration, int $eventsCreated): void
    {
        $this->info("✅ Initial scan completed successfully!");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Directories found', number_format($stats['directories'])],
                ['Files found', number_format($stats['files'])],
                ['Total size', $this->formatBytes($stats['total_size'])],
                ['Errors', $stats['errors']],
                ['Events created', number_format($eventsCreated)],
                ['Duration', "{$duration} seconds"],
                ['Items per second', $stats['directories'] + $stats['files'] > 0 ?
                    round(($stats['directories'] + $stats['files']) / $duration) : 0],
                ['Event types', 'Directory: '.$stats['directories'].', File: '.$stats['files']],
            ]
        );
    }

    private function showSampleEvents(): void
    {
        $this->newLine();
        $this->info("📋 Sample events created (last 5):");

        $sampleEvents = DB::table('stored_events')
            ->where('event_class', 'like', '%FileSystem%') // Filter filesystem events
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['event_class', 'event_properties', 'created_at']);

        if ($sampleEvents->isEmpty()) {
            $this->warn("No events found.");
            return;
        }

        $tableData = $sampleEvents->map(function ($event) {
            $properties = json_decode($event->event_properties, true);

            return [
                'Event' => class_basename($event->event_class),
                'Path' => $properties['path'] ? basename($properties['path']) : 'N/A',
                'Type' => $properties['type'] ?? 'N/A',
                'Created' => $event->created_at,
            ];
        })->toArray();

        $this->table(
            ['Event', 'Path', 'Type', 'Created'],
            $tableData
        );
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
