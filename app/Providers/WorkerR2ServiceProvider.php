<?php

namespace App\Providers;

use App\Services\Storage\WorkerR2Adapter;
use App\Services\Storage\WorkerR2Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class WorkerR2ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('worker-r2', function ($app, array $config) {
            $client = new WorkerR2Client(
                workerUrl: (string) ($config['url'] ?? ''),
                authToken: (string) ($config['token'] ?? ''),
                sharedSecret: (string) ($config['secret'] ?? ''),
                timeout: (int) ($config['timeout'] ?? 120),
            );

            $adapter = new WorkerR2Adapter($client);
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
