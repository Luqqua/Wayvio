<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BrandingSetSiteAssetCommand extends BaseModCommand
{
    protected $signature = 'branding:set-site-asset
        {type : logo or favicon}
        {source : Absolute path or project-relative path to the image file}';

    protected $description = 'Setzt das globale Site-Logo oder Favicon per CLI.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $type = strtolower((string) $this->argument('type'));
        if (!in_array($type, ['logo', 'favicon'], true)) {
            $this->error("Invalid type '{$type}'. Use 'logo' or 'favicon'.");
            return Command::FAILURE;
        }

        $sourcePath = $this->resolveSourcePath((string) $this->argument('source'));
        if ($sourcePath === null || !is_file($sourcePath) || !is_readable($sourcePath)) {
            $this->error('Source file not found or not readable.');
            return Command::FAILURE;
        }

        $fileSize = @filesize($sourcePath);
        if ($fileSize === false || $fileSize > (2 * 1024 * 1024)) {
            $this->error('The image must be 2 MB or smaller.');
            return Command::FAILURE;
        }

        $imageInfo = @getimagesize($sourcePath);
        $imageType = $imageInfo[2] ?? null;
        $allowedTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (!$imageInfo || $imageType === null || !isset($allowedTypes[$imageType])) {
            $this->error('Only JPG, JPEG, PNG and WebP images are allowed.');
            return Command::FAILURE;
        }

        $targetDir = base_path('assets/wayvio/images');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $prefix = $type === 'logo' ? 'avatar' : 'favicon';
        $extension = $allowedTypes[$imageType];
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $prefix . '_' . time() . '.' . $extension;
        if (!copy($sourcePath, $targetPath)) {
            $this->error('Failed to copy the image to the branding directory.');
            return Command::FAILURE;
        }

        if (extension_loaded('imagick')) {
            try {
                $image = new \Imagick($targetPath);
                $image->stripImage();
                $image->writeImage($targetPath);
            } catch (\Throwable $exception) {
                $this->warn('Image copied, but metadata stripping with Imagick failed.');
            }
        }

        $this->deleteExistingAsset($targetDir, $prefix, $targetPath);
        $this->info(ucfirst($type) . ' updated successfully.');
        $this->line($targetPath);

        return Command::SUCCESS;
    }

    protected function resolveSourcePath(string $source): ?string
    {
        $directPath = realpath($source);
        if ($directPath !== false) {
            return $directPath;
        }

        $projectPath = realpath(base_path($source));
        if ($projectPath !== false) {
            return $projectPath;
        }

        return null;
    }

    protected function deleteExistingAsset(string $directory, string $prefix, ?string $exceptPath = null): void
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '(_\w+)?\.\w+$/i';
        $exceptPath = $exceptPath ? realpath($exceptPath) : null;

        foreach (File::files($directory) as $file) {
            $currentPath = realpath($file->getPathname()) ?: $file->getPathname();

            if ($exceptPath !== null && $currentPath === $exceptPath) {
                continue;
            }

            if (preg_match($pattern, $file->getFilename())) {
                File::delete($file->getPathname());
            }
        }
    }
}
