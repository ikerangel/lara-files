<?php

namespace App\Services;

use App\Events\FileSystem\DirectoryCreated;
use App\Events\FileSystem\DirectoryDeleted;
use App\Events\FileSystem\FileCreated;
use App\Events\FileSystem\FileDeleted;
use App\Events\FileSystem\FileModified;
use Illuminate\Support\Facades\Log;
use Spatie\Watcher\Watch;

class FileSystemWatcher
{
    private string $basePath;
    private array $fileHashes = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->loadExistingHashes();
    }

    public function start(): void
    {
        Log::info("Starting file system watcher", ['path' => $this->basePath]);

        Watch::path($this->basePath)
            ->onFileCreated(function (string $path) {
                $this->handleFileCreated($path);
            })
            ->onFileUpdated(function (string $path) {
                $this->handleFileUpdated($path);
            })
            ->onFileDeleted(function (string $path) {
                $this->handleFileDeleted($path);
            })
            ->onDirectoryCreated(function (string $path) {
                $this->handleDirectoryCreated($path);
            })
            ->onDirectoryDeleted(function (string $path) {
                $this->handleDirectoryDeleted($path);
            })
            ->start();
    }

    private function handleFileCreated(string $absolutePath): void
    {
        try {
            $relativePath = $this->getRelativePath($absolutePath);

            if (!file_exists($absolutePath)) {
                Log::warning("File creation event received but file doesn't exist", ['path' => $absolutePath]);
                return;
            }

            $hash = $this->calculateFileHash($absolutePath);
            $modifiedAt = new \DateTime('@' . filemtime($absolutePath));
            $size = filesize($absolutePath);

            // Store hash for future comparison
            $this->fileHashes[$relativePath] = $hash;

            event(new FileCreated($relativePath, 'real-time', $hash, $modifiedAt, $size));

            Log::info("File created", [
                'path' => $relativePath,
                'size' => $size,
                'hash' => substr($hash, 0, 8) . '...'
            ]);

        } catch (\Exception $e) {
            Log::error("Error handling file creation", [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleFileUpdated(string $absolutePath): void
    {
        try {
            $relativePath = $this->getRelativePath($absolutePath);

            if (!file_exists($absolutePath)) {
                Log::warning("File update event received but file doesn't exist", ['path' => $absolutePath]);
                return;
            }

            $newHash = $this->calculateFileHash($absolutePath);
            $modifiedAt = new \DateTime('@' . filemtime($absolutePath));
            $size = filesize($absolutePath);
            $previousHash = $this->fileHashes[$relativePath] ?? null;

            // Only emit event if content actually changed
            if ($newHash !== $previousHash) {
                $this->fileHashes[$relativePath] = $newHash;

                event(new FileModified(
                    $relativePath,
                    'real-time',
                    $newHash,
                    $modifiedAt,
                    $size,
                    $previousHash
                ));

                Log::info("File modified", [
                    'path' => $relativePath,
                    'size' => $size,
                    'old_hash' => $previousHash ? substr($previousHash, 0, 8) . '...' : 'unknown',
                    'new_hash' => substr($newHash, 0, 8) . '...'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error handling file update", [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleFileDeleted(string $absolutePath): void
    {
        try {
            $relativePath = $this->getRelativePath($absolutePath);

            // Remove from hash tracking
            unset($this->fileHashes[$relativePath]);

            event(new FileDeleted($relativePath, 'real-time'));

            Log::info("File deleted", ['path' => $relativePath]);

        } catch (\Exception $e) {
            Log::error("Error handling file deletion", [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDirectoryCreated(string $absolutePath): void
    {
        try {
            $relativePath = $this->getRelativePath($absolutePath);

            event(new DirectoryCreated($relativePath, 'real-time'));

            Log::info("Directory created", ['path' => $relativePath]);

        } catch (\Exception $e) {
            Log::error("Error handling directory creation", [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDirectoryDeleted(string $absolutePath): void
    {
        try {
            $relativePath = $this->getRelativePath($absolutePath);

            // Remove all file hashes for files in this directory
            $this->fileHashes = array_filter(
                $this->fileHashes,
                fn($path) => !str_starts_with($path, $relativePath . '/'),
                ARRAY_FILTER_USE_KEY
            );

            event(new DirectoryDeleted($relativePath, 'real-time'));

            Log::info("Directory deleted", ['path' => $relativePath]);

        } catch (\Exception $e) {
            Log::error("Error handling directory deletion", [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function calculateFileHash(string $filePath): ?string
    {
        try {
            // For large files, use MD5 for performance
            if (filesize($filePath) > 100 * 1024 * 1024) { // 100MB
                return hash_file('md5', $filePath);
            }
            return hash_file('sha256', $filePath);
        } catch (\Exception $e) {
            Log::warning("Could not calculate hash for file", [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getRelativePath(string $absolutePath): string
    {
        return str_replace($this->basePath . DIRECTORY_SEPARATOR, '', $absolutePath);
    }

    private function loadExistingHashes(): void
    {
        // TODO: Load existing file hashes from database/cache
        // This would help detect modifications during watcher downtime
        Log::info("Loading existing file hashes for comparison");
    }
}
