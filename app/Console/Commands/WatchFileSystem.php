<?php

namespace App\Console\Commands;

use App\Services\FileSystemWatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WatchFileSystem extends Command
{
    protected $signature = 'filesystem:watch
                          {path : The path to watch}
                          {--timeout=0 : Stop watching after N seconds (0 = infinite)}';

    protected $description = 'Watch file system for real-time changes';

    public function handle()
    {
        $path = $this->argument('path');
        $timeout = (int) $this->option('timeout');

        if (!is_dir($path)) {
            $this->error("Path does not exist or is not a directory: {$path}");
            return Command::FAILURE;
        }

        $this->info("🔍 Starting file system watcher...");
        $this->info("📁 Watching: {$path}");

        if ($timeout > 0) {
            $this->info("⏱️  Timeout: {$timeout} seconds");
        } else {
            $this->info("⏱️  Running indefinitely (Ctrl+C to stop)");
        }

        $this->newLine();

        // Set up signal handling for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'gracefulShutdown']);
            pcntl_signal(SIGINT, [$this, 'gracefulShutdown']);
        }

        try {
            $watcher = new FileSystemWatcher($path);

            // Set timeout if specified
            if ($timeout > 0) {
                $this->setTimeoutAlarm($timeout);
            }

            $this->info("✅ Watcher started. Monitoring for changes...");
            $this->displayInstructions();

            // Start watching (this will block)
            $watcher->start();

        } catch (\Exception $e) {
            $this->error("❌ Watcher failed: " . $e->getMessage());
            Log::error("File system watcher failed", [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayInstructions(): void
    {
        $this->newLine();
        $this->line("📋 <fg=yellow>Instructions:</fg=yellow>");
        $this->line("   • Create, modify, or delete files/folders in the watched directory");
        $this->line("   • Check the logs for real-time event tracking");
        $this->line("   • Use Ctrl+C to stop watching gracefully");
        $this->line("   • Check stored_events table to see captured events");
        $this->newLine();
    }

    private function setTimeoutAlarm(int $seconds): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_alarm($seconds);
            pcntl_signal(SIGALRM, function () {
                $this->info("⏰ Timeout reached. Stopping watcher...");
                exit(0);
            });
        }
    }

    public function gracefulShutdown(): void
    {
        $this->newLine();
        $this->info("🛑 Shutting down file system watcher gracefully...");
        $this->info("✅ Watcher stopped.");
        exit(0);
    }
}
