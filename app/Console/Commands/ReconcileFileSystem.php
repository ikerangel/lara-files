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

    // Constructor to set custom help message
    public function __construct()
    {
        parent::__construct();

        $this->setHelp(<<<'HELP'
DESCRIPTION:
  Reconcile the current file system state with stored event history.

  This command will:
  1. Scan the target directory (unless skipped)
  2. Compare current state against stored events
  3. Identify discrepancies (missing/modified files)
  4. Generate reconciliation events to fix inconsistencies
  5. Output summary statistics

  💡 Recommended for periodic maintenance and data integrity checks.

WORKFLOW:
  [Scan Phase]      : Scan file system (optional, unless --skip-scan used)
  [Comparison Phase]: Compare against last known state from events
  [Reconciliation]  : Generate events to fix discrepancies
  [Reporting]       : Show results table

USAGE:
  php artisan filesystem:reconcile <path> [options]

ARGUMENTS:
  path              Absolute path to reconcile (required)

OPTIONS:
  --skip-scan       Use last scan state instead of re-scanning (faster)

OUTPUT:
  - Summary table with reconciliation metrics:
      • Items scanned
      • Discrepancies found
      • Events created
      • Duration
      • Items per second

EXAMPLES:
  1. Full reconciliation with new scan:
     php artisan filesystem:reconcile /var/www

  2. Fast reconciliation using last scan state:
     php artisan filesystem:reconcile /mnt/data --skip-scan

SAMPLE OUTPUT:

  Starting file system reconciliation...
  Path: /var/www

  ✅ Reconciliation completed successfully!

  ┌───────────────────────────────┬──────────┐
  │ Metric                        │ Value    │
  ├───────────────────────────────┼──────────┤
  │ Items scanned                 │ 15,342   │
  │ Discrepancies found           │ 27       │
  │ Reconciliation events created │ 27       │
  │ Duration                      │ 8.3 sec  │
  │ Items per second              │ 1,848    │
  └───────────────────────────────┴──────────┘

NOTES:
  - Without --skip-scan: Does fresh scan (slower but more accurate)
  - With --skip-scan: Uses last scan state (faster but requires recent scan)
  - Discrepancies include: missing files, modified files, orphan events
  - Created events will fix inconsistencies in the event history
  - Run during low-traffic periods for large directories
HELP
        );
    }

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
