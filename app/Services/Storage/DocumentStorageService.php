<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\EncryptedFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage service providing a provider-agnostic abstraction for file and document storage.
 * Decouples domain logic from underlying filesystem drivers (local disk, AWS S3, etc.)
 * and ensures safe file handling, structured folder organization, and secure downloads.
 */
class DocumentStorageService
{
    /**
     * Get the configured disk for public assets (e.g. property photos).
     */
    public function getPublicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    /**
     * Get the configured disk for private documents (e.g. receipts, invoices, attachments).
     */
    public function getPrivateDisk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * Store a publicly accessible file and return its storage path.
     */
    public function storePublic(UploadedFile $file, string $directory): string
    {
        $safeName = $this->generateSafeFilename($file);
        $disk = $this->getPublicDisk();

        return (string) $file->storeAs(trim($directory, '/'), $safeName, $disk);
    }

    /**
     * Store a private unencrypted document and return its storage path.
     */
    public function storePrivate(UploadedFile $file, string $directory): string
    {
        $safeName = $this->generateSafeFilename($file);
        $disk = $this->getPrivateDisk();

        return (string) $file->storeAs(trim($directory, '/'), $safeName, $disk);
    }

    /**
     * Store a private encrypted document (e.g. ID cards, lease contracts) using AES encryption.
     */
    public function storeEncrypted(UploadedFile|string $file, string $directory): string
    {
        $safeName = $file instanceof UploadedFile
            ? $this->generateSafeFilename($file)
            : Str::random(40).'.bin';

        $fullPath = trim($directory, '/').'/'.$safeName;
        $disk = $this->getPrivateDisk();

        EncryptedFileService::put($file, $fullPath, $disk);

        return $fullPath;
    }

    /**
     * Safely delete a file from storage, checking existence on multiple disks if necessary.
     */
    public function delete(?string $path, ?string $disk = null): bool
    {
        if (empty($path)) {
            return false;
        }

        $targetDisk = $disk ?? $this->getPrivateDisk();

        if (Storage::disk($targetDisk)->exists($path)) {
            return Storage::disk($targetDisk)->delete($path);
        }

        // Check public disk if not found on primary disk (fallback for legacy paths)
        $publicDisk = $this->getPublicDisk();
        if ($targetDisk !== $publicDisk && Storage::disk($publicDisk)->exists($path)) {
            return Storage::disk($publicDisk)->delete($path);
        }

        return false;
    }

    /**
     * Check if a file exists on the designated or fallback disk.
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        $targetDisk = $disk ?? $this->getPrivateDisk();

        if (Storage::disk($targetDisk)->exists($path)) {
            return true;
        }

        $publicDisk = $this->getPublicDisk();
        if ($targetDisk !== $publicDisk && Storage::disk($publicDisk)->exists($path)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the public URL for an asset.
     */
    public function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Storage::disk($this->getPublicDisk())->url($path);
    }

    /**
     * Stream a download response without assuming a local filesystem path.
     * Works identically across local filesystems and cloud object storage (AWS S3).
     */
    public function downloadResponse(string $path, ?string $filename = null, ?string $disk = null): StreamedResponse
    {
        $targetDisk = $this->resolveDiskForPath($path, $disk);

        if (! Storage::disk($targetDisk)->exists($path)) {
            abort(404, 'الملف المطلوب غير موجود في وحدة التخزين.');
        }

        $downloadName = $filename ?: basename($path);

        return Storage::disk($targetDisk)->download($path, $downloadName);
    }

    /**
     * Stream an inline preview response without assuming a local filesystem path.
     * Compatible with local disk and remote S3 drivers.
     */
    public function previewResponse(
        string $path,
        ?string $mimeType = null,
        ?string $filename = null,
        ?string $disk = null
    ): StreamedResponse|Response {
        $targetDisk = $this->resolveDiskForPath($path, $disk);

        if (! Storage::disk($targetDisk)->exists($path)) {
            abort(404, 'الملف المطلوب غير موجود في وحدة التخزين.');
        }

        $resolvedMime = $mimeType ?: Storage::disk($targetDisk)->mimeType($path) ?: 'application/octet-stream';
        $displayFilename = $filename ?: basename($path);

        return Storage::disk($targetDisk)->response($path, $displayFilename, [
            'Content-Type' => $resolvedMime,
            'Content-Disposition' => 'inline; filename="'.addslashes($displayFilename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Generate a cryptographically random, sanitized filename with a lowercase extension.
     */
    public function generateSafeFilename(UploadedFile $file): string
    {
        $originalExtension = $file->getClientOriginalExtension() ?: 'bin';
        $safeExtension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $originalExtension) ?: 'bin');

        return Str::random(40).'.'.$safeExtension;
    }

    /**
     * Resolve which disk holds the file, checking primary and fallback disks.
     */
    protected function resolveDiskForPath(string $path, ?string $preferredDisk = null): string
    {
        if ($preferredDisk !== null && Storage::disk($preferredDisk)->exists($path)) {
            return $preferredDisk;
        }

        $privateDisk = $this->getPrivateDisk();
        if (Storage::disk($privateDisk)->exists($path)) {
            return $privateDisk;
        }

        $publicDisk = $this->getPublicDisk();
        if (Storage::disk($publicDisk)->exists($path)) {
            return $publicDisk;
        }

        return $preferredDisk ?? $privateDisk;
    }
}
