<?php

namespace Database\Factories;

use App\Models\FileDirectory;
use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileEntry>
 */
class FileEntryFactory extends Factory
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
            'owner_user_id' => User::factory(),
            'original_name' => fake()->unique()->words(2, true).'.txt',
            'disk' => 'local',
            'path' => 'files/'.fake()->uuid().'.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 128,
        ];
    }
}
