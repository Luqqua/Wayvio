<?php

namespace App\Jobs;

use App\Services\Uploads\ResponsiveImageVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateResponsiveMediaVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $diskName,
        public string $key
    ) {
    }

    public function handle(ResponsiveImageVariantService $variantService): void
    {
        $variantService->generateForKey($this->diskName, $this->key);
    }
}

