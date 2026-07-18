<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('generated avatars are assigned only to users without an avatar', function () {
    Storage::fake('public');

    $existingAvatarUser = User::factory()->create([
        'avatar_path' => 'avatars/existing/custom.png',
    ]);
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->artisan('app:users:assign-avatars')
        ->expectsOutput('Assigned generated avatars to 2 user(s).')
        ->assertSuccessful();

    $existingAvatarUser->refresh();
    $firstUser->refresh();
    $secondUser->refresh();

    expect($existingAvatarUser->avatar_path)->toBe('avatars/existing/custom.png')
        ->and($firstUser->avatar_path)->toBe('avatars/'.$firstUser->id.'/generated.png')
        ->and($secondUser->avatar_path)->toBe('avatars/'.$secondUser->id.'/generated.png');

    Storage::disk('public')->assertExists($firstUser->avatar_path);
    Storage::disk('public')->assertExists($secondUser->avatar_path);

    $firstAvatar = Storage::disk('public')->get($firstUser->avatar_path);
    $secondAvatar = Storage::disk('public')->get($secondUser->avatar_path);
    $firstAvatarSize = getimagesizefromstring($firstAvatar);
    $secondAvatarSize = getimagesizefromstring($secondAvatar);

    expect($firstAvatar)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($secondAvatar)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($firstAvatarSize)->toBeArray()
        ->and($firstAvatarSize[0])->toBe(512)
        ->and($firstAvatarSize[1])->toBe(512)
        ->and($secondAvatarSize)->toBeArray()
        ->and($secondAvatarSize[0])->toBe(512)
        ->and($secondAvatarSize[1])->toBe(512)
        ->and(hash('sha256', $firstAvatar))->not->toBe(hash('sha256', $secondAvatar));

    $this->artisan('app:users:assign-avatars')
        ->expectsOutput('Assigned generated avatars to 0 user(s).')
        ->assertSuccessful();
});
