<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\UserAvatarGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('app:users:assign-avatars')]
#[Description('Generate distinct local avatars for users who do not have one')]
class AssignUserAvatarsCommand extends Command
{
    public function __construct(
        private readonly UserAvatarGenerator $avatarGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $assignedUsers = 0;

        foreach (User::query()->whereNull('avatar_path')->lazyById() as $user) {
            $avatarPath = 'avatars/'.$user->getKey().'/generated.png';
            $avatar = $this->avatarGenerator->generate($user);

            if (! Storage::disk('public')->put($avatarPath, $avatar)) {
                throw new RuntimeException("Unable to store avatar for user [{$user->getKey()}].");
            }

            $user->forceFill(['avatar_path' => $avatarPath])->save();
            $assignedUsers++;
        }

        $this->info("Assigned generated avatars to {$assignedUsers} user(s).");

        return self::SUCCESS;
    }
}
