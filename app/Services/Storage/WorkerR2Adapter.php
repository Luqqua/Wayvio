<?php

namespace App\Services\Storage;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

/**
 * Flysystem-Adapter für den R2-Backup-Worker.
 *
 * Schreiboperationen und Listing laufen über WorkerR2Client
 * und damit über den Cloudflare Worker, der Quota-Enforcement übernimmt.
 *
 * Leseoperationen sind nur für manifest.json erlaubt, damit UI/Status aktuelle
 * Backup-Metadaten anzeigen können. Restore-Artefakte werden manuell aus
 * Cloudflare R2 heruntergeladen und per SCP auf dem Server bereitgestellt.
 * Löschoperationen für einzelne Objekte sind bewusst deaktiviert.
 *
 * Nicht implementierte Operationen (read, delete, move, copy, deleteDirectory) werden
 * nie vom Backup-System aufgerufen und werfen eine RuntimeException.
 */
class WorkerR2Adapter implements FilesystemAdapter
{
    public function __construct(private readonly WorkerR2Client $client) {}

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->findObject($path) !== null;
        } catch (\Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        // R2 kennt keine echten Verzeichnisse.
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            $this->client->upload($path, $stream);
            fclose($stream);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->client->upload($path, $contents);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $stream = $this->client->download($path);
            $content = stream_get_contents($stream);
            fclose($stream);
            return (string) $content;
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            return $this->client->download($path);
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->client->delete($path);
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        throw new \RuntimeException('WorkerR2Adapter unterstützt deleteDirectory nicht.');
    }

    public function createDirectory(string $path, Config $config): void
    {
        // R2 kennt keine echten Verzeichnisse – kein Op nötig.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // R2 unterstützt keine Flysystem-Visibility.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $obj = $this->client->findObject($path);
            $timestamp = ($obj && $obj['last_modified']) ? strtotime($obj['last_modified']) : null;
            return new FileAttributes($path, null, null, $timestamp ?: null);
        } catch (\Throwable) {
            return new FileAttributes($path);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $obj = $this->client->findObject($path);
            return new FileAttributes($path, $obj['size_bytes'] ?? null);
        } catch (\Throwable) {
            return new FileAttributes($path);
        }
    }

    /**
     * @return iterable<FileAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $objects = $this->client->list($path);
        } catch (\Throwable) {
            return;
        }

        foreach ($objects as $obj) {
            $timestamp = ($obj['last_modified'] ?? null) ? strtotime($obj['last_modified']) : null;
            yield new FileAttributes(
                $obj['key'],
                $obj['size_bytes'] ?? null,
                'private',
                $timestamp ?: null,
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw new \RuntimeException('WorkerR2Adapter unterstützt move nicht.');
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw new \RuntimeException('WorkerR2Adapter unterstützt copy nicht.');
    }
}
