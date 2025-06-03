<?php

namespace App\Providers;

// Core Laravel events
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

// File System Events
use App\Events\FileSystem\DirectoryCreated;
use App\Events\FileSystem\DirectoryDeleted;
use App\Events\FileSystem\DirectoryRenamed;
use App\Events\FileSystem\FileCreated;
use App\Events\FileSystem\FileDeleted;
use App\Events\FileSystem\FileModified;
use App\Events\FileSystem\FileRenamed;

// Listener
use App\Listeners\FileSystemEventListener;

// Framework
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Existing user registration event
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // File system events using the same listener
        ...array_fill_keys([
            FileCreated::class,
            FileModified::class,
            FileDeleted::class,
            FileRenamed::class,
            DirectoryCreated::class,
            DirectoryDeleted::class,
            DirectoryRenamed::class,
        ], [FileSystemEventListener::class]),
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
