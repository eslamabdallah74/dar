<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EncryptedFileService
{
    /**
     * Store an uploaded file or raw binary content with AES encryption on disk.
     */
    public static function put(UploadedFile|string $file, string $path, string $disk = 'local'): string
    {
        $rawBytes = $file instanceof UploadedFile ? $file->getContent() : $file;
        $encryptedPayload = Crypt::encrypt($rawBytes);

        Storage::disk($disk)->put($path, $encryptedPayload);

        return $path;
    }

    /**
     * Retrieve and decrypt a file from disk.
     * Gracefully falls back to raw content if the file was stored unencrypted (legacy/seeders).
     */
    public static function get(string $path, string $disk = 'local'): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $storedBytes = Storage::disk($disk)->get($path);

        try {
            return Crypt::decrypt($storedBytes);
        } catch (DecryptException) {
            // Backward compatibility for legacy or unencrypted files
            return $storedBytes;
        }
    }

    /**
     * Check if a file on disk is encrypted.
     */
    public static function isEncrypted(string $path, string $disk = 'local'): bool
    {
        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $storedBytes = Storage::disk($disk)->get($path);

        try {
            Crypt::decrypt($storedBytes);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Build a secure HTTP response streaming the decrypted file with strict private headers.
     */
    public static function response(
        string $path,
        string $disk = 'local',
        ?string $downloadFilename = null,
        bool $forceDownload = false
    ): Response {
        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'الملف المطلوب غير موجود في وحدة التخزين الآمن.');
        }

        $decryptedContent = self::get($path, $disk);

        if ($decryptedContent === null) {
            abort(404, 'تعذر قراءة محتوى الملف.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decryptedContent) ?: 'application/octet-stream';
        $fallbackFilename = basename($path);
        $filename = $downloadFilename ?: $fallbackFilename;

        // If the downloadFilename doesn't have an extension, add one matching the mime
        if (! pathinfo($filename, PATHINFO_EXTENSION)) {
            $ext = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => pathinfo($path, PATHINFO_EXTENSION) ?: 'bin',
            };
            $filename .= '.'.$ext;
        }

        $disposition = $forceDownload ? 'attachment' : 'inline';

        return response($decryptedContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($decryptedContent),
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Delete a file from disk.
     */
    public static function delete(?string $path, string $disk = 'local'): bool
    {
        if (! $path) {
            return false;
        }

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }
}
