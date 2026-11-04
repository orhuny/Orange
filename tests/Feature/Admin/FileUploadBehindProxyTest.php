<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\withHeaders;

// Behind Cloudflare the app is reached over plain http while every URL it builds is https
// (AppServiceProvider). Admin uploads (e.g. game thumbnails) go to an https signed URL, so
// the request's forwarded scheme must be trusted or the signature never matches (401).
it('accepts admin uploads that Cloudflare forwards over plain http', function (): void {
    Storage::fake('tmp-for-tests');
    URL::forceScheme('https');
    $signed = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));

    withHeaders(['X-Forwarded-Proto' => 'https'])
        ->post(str_replace('https://', 'http://', $signed), ['files' => [UploadedFile::fake()->image('thumb.png')]])
        ->assertOk()
        ->assertJsonCount(1, 'paths');
});

it('still refuses an upload whose signature does not match', function (): void {
    Storage::fake('tmp-for-tests');
    URL::forceScheme('https');
    $signed = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));

    withHeaders(['X-Forwarded-Proto' => 'https'])
        ->post(str_replace('https://', 'http://', $signed).'0', ['files' => [UploadedFile::fake()->image('thumb.png')]])
        ->assertUnauthorized();
});
