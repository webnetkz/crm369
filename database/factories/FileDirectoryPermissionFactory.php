<?php

namespace Database\Factories;

use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileDirectoryPermission>
 */
class FileDirectoryPermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_directory_id' => FileDirectory::factory(),
            'user_id' => User::factory(),
            'user_group_id' => null,
            'granted_by_user_id' => User::factory(),
            'access_level' => FileDirectoryPermission::ACCESS_READ,
        ];
    }

    public function forGroup(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'user_group_id' => UserGroup::factory(),
        ]);
    }
}
