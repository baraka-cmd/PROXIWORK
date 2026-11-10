<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Settings\PlatformSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FileStorageService
{
    public function __construct(private readonly PlatformSettingService $settings) {}

/** @var list<string> */
    private const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    private const DOCUMENT_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function storeAvatar(UploadedFile $file, int|string $userId): string
    {
        $this->validate($file, self::IMAGE_MIMES, (int) $this->settings->get('media.maximum_image_kb'));

        return $this->storePublic($file, 'avatars/'.((string) $userId));
    }

    public function storeServiceImage(UploadedFile $file, int|string $serviceId): string
    {
        $this->validate($file, self::IMAGE_MIMES, (int) $this->settings->get('media.maximum_image_kb'));

        return $this->storePublic($file, 'service-images/'.((string) $serviceId));
    }

    public function storeDocument(UploadedFile $file, int|string $userId): string
    {
        $this->validate($file, self::DOCUMENT_MIMES, (int) $this->settings->get('media.maximum_document_kb'));

        return $this->storePrivate($file, 'documents/'.((string) $userId));
    }

    public function storeVerificationFile(UploadedFile $file, int|string $professionalId): string
    {
        $this->validate($file, self::DOCUMENT_MIMES, (int) $this->settings->get('media.maximum_document_kb'));

        return $this->storePrivate($file, 'verification-files/'.((string) $professionalId));
    }

    public function deletePublic(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    public function deletePrivate(string $path): void
    {
        Storage::disk('local')->delete($path);
    }

    private function storePublic(UploadedFile $file, string $directory): string
    {
        return $this->store($file, 'public', $directory);
    }

    private function storePrivate(UploadedFile $file, string $directory): string
    {
        return $this->store($file, 'local', $directory);
    }

    private function store(UploadedFile $file, string $disk, string $directory): string
    {
        $path = $file->store($directory, $disk);

        if ($path === false) {
            throw new RuntimeException('Unable to store uploaded file.');
        }

        return $path;
    }

    private function validate(UploadedFile $file, array $allowedExtensions, int $maxKb): void
    {
        if ($file->isValid() === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is invalid.',
            ]);
        }

        $extension = strtolower((string) $file->extension());

        if (in_array($extension, $allowedExtensions, true) === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file type is not allowed.',
            ]);
        }

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is too large.',
            ]);
        }
    }
}
