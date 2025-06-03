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
