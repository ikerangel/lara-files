<?php

namespace App\Console\Commands;

use App\Events\FileSystem\FileCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEventDispatch extends Command
{
    protected $signature = 'test:event';
    protected $description = 'Test event dispatching';

    public function handle()
    {
        Log::info("Dispatching test event");

        event(new FileCreated(
            '/test/path.txt',
            'test',
            'hash123',
            now(),
            1024
        ));

        Log::info("Test event dispatched");

        $this->info("✅ Test event dispatched. Check logs.");
        return 0;
    }
}
