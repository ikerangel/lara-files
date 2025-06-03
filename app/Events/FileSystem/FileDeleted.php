<?php

namespace App\Events\FileSystem;

class FileDeleted extends FileSystemEvent
{
    public function __construct(string $path, string $origin = 'real-time')
    {
        parent::__construct($path, $origin);
    }
}
