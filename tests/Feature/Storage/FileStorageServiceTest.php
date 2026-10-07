<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_uses_public_generated_path(): void
    {
        Storage::fake('public');

        $path = app(FileStorageService::class)->storeAvatar(
            UploadedFile::fake()->image('avatar.jpg'),
            42,
        );

        $this->assertStringStartsWith('avatars/42/', $path);
        $this->assertStringNotContainsString('avatar.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_verification_file_uses_private_generated_path(): void
    {
        Storage::fake('local');

        $path = app(FileStorageService::class)->storeVerificationFile(
            UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
            7,
        );

        $this->assertStringStartsWith('verification-files/7/', $path);
        $this->assertStringNotContainsString('identity.pdf', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        app(FileStorageService::class)->storeAvatar(
            UploadedFile::fake()->create('script.php', 10, 'text/x-php'),
            42,
        );
    }

    public function test_oversized_document_is_rejected(): void
    {
        Storage::fake('local');

        $this->expectException(ValidationException::class);

        app(FileStorageService::class)->storeDocument(
            UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            42,
        );
    }
}
