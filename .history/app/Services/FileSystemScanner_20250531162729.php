<?php

namespace App\Services;

use App\Events\FileSystem\DirectoryCreated;
use App\Events\FileSystem\FileCreated;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileSystemScanner
{
    private string $basePath;
    private int $totalItems = 0;
    private int $processedItems = 0;
    private array $stats = [
        'directories' => 0,
        'files' => 0,
        'total_size' => 0,
        'errors' => 0,
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function scan(callable $progressCallback = null): array
    {
        Log::info("Starting initial file system scan", ['path' => $this->basePath]);

        if (!is_dir($this->basePath)) {
            throw new \InvalidArgumentException("Path does not exist or is not a directory: {$this->basePath}");
        }

        // First pass: count total items for progress tracking
        $this->countItems();

        // Second pass: process items and emit events
        $this->processItems($progressCallback);

        Log::info("File system scan completed", $this->stats);

        return $this->stats;
    }

    private function countItems(): void
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $this->totalItems = iterator_count($iterator);
        } catch (\Exception $e) {
            Log::error("Error counting items", ['error' => $e->getMessage()]);
            $this->totalItems = 0;
        }
    }

    private function processItems(callable $progressCallback = null): void
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $fileInfo) {
                try {
                    $this->processItem($fileInfo);
                    $this->processedItems++;

                    if ($progressCallback && $this->totalItems > 0) {
                        $progress = ($this->processedItems / $this->totalItems) * 100;
                        $progressCallback($progress, $this->processedItems, $this->totalItems, $fileInfo->getPathname());
                    }
                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    Log::error("Error processing item", [
                        'path' => $fileInfo->getPathname(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error during file system scan", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function processItem(SplFileInfo $fileInfo): void
    {
        $relativePath = $this->getRelativePath($fileInfo->getPathname());

        if ($fileInfo->isDir()) {
            $this->processDirectory($relativePath);
        } else {
            $this->processFile($fileInfo, $relativePath);
        }
    }

    private function processDirectory(string $relativePath): void
    {
        event(new DirectoryCreated($relativePath, 'initial'));
        $this->stats['directories']++;
    }

    private function processFile(SplFileInfo $fileInfo, string $relativePath): void
    {
        $hash = $this->calculateFileHash($fileInfo->getPathname());
        $modifiedAt = new \DateTime('@' . $fileInfo->getMTime());
        $size = $fileInfo->getSize();

        event(new FileCreated(
            $relativePath,
            'initial',
            $hash,
            $modifiedAt,
            $size
        ));

        $this->stats['files']++;
        $this->stats['total_size'] += $size;
    }

    private function calculateFileHash(string $filePath): ?string
    {
        try {
            // For large files, we might want to use a more efficient method
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

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getProgress(): float
    {
        return $this->totalItems > 0 ? ($this->processedItems / $this->totalItems) * 100 : 0;
    }
}
