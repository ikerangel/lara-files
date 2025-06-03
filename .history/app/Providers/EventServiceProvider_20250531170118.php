<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use App\Events\FileSystem\FileCreated;
use App\Events\FileSystem\FileDeleted;
use App\Events\FileSystem\FileRenamed;
use Illuminate\Auth\Events\Registered;
use App\Events\FileSystem\FileModified;
use App\Listeners\FileSystemEventListener;
use App\Events\FileSystem\DirectoryCreated;
use App\Events\FileSystem\DirectoryDeleted;
use App\Events\FileSystem\DirectoryRenamed;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
                // File System Events
        FileCreated::class => [
            FileSystemEventListener::class,
        ],
        FileModified::class => [
            FileSystemEventListener::class,
        ],
        FileDeleted::class => [
            FileSystemEventListener::class,
        ],
        FileRenamed::class => [
            FileSystemEventListener::class,
        ],
        DirectoryCreated::class => [
            FileSystemEventListener::class,
        ],
        DirectoryDeleted::class => [
            FileSystemEventListener::class,
        ],
        DirectoryRenamed::class => [
            FileSystemEventListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
