<?php

namespace App\Console\Commands;

use App\Services\FileSystemReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileFileSystem extends Command
{
    protected $signature = 'filesystem:reconcile
                          {path : The path to reconcile}
                          {--skip-scan : Skip filesystem scan, use last state}';

    protected $description = 'Reconcile file system state with event history';

    public function handle()
    {
        $path = $this->argument('path');

        $this->info("Starting file system reconciliation...");
        $this->line("Path: {$path}");
        $this->newLine();

        $startTime = microtime(true);

        try {
            $reconciler = new FileSystemReconciler($path);
            $result = $reconciler->execute();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->displayResults($result, $duration);

        } catch (\Exception $e) {
            $this->error("Reconciliation failed: " . $e->getMessage());
            Log::error("Reconciliation failed", ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayResults(array $result, float $duration): void
    {
        $this->info("✅ Reconciliation completed successfully!");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Items scanned', number_format($result['scanned'])],
                ['Discrepancies found', number_format($result['discrepancies'])],
                ['Reconciliation events created', number_format($result['events_created'])],
                ['Duration', "{$duration} seconds"],
                ['Items per second', $result['scanned'] > 0 ? round($result['scanned'] / $duration) : 0],
            ]
        );
    }
}
