<?php

namespace App\Events\FileSystem;


class DirectoryCreated extends FileSystemEvent
{
    public function __construct(string $path, string $origin = 'real-time')
    {
        parent::__construct($path, $origin);
    }
}
