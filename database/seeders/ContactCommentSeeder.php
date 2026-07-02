<?php

namespace Database\Seeders;

use App\Models\ContactComment;
use Illuminate\Database\Seeder;

class ContactCommentSeeder extends Seeder
{
    public function run(): void
    {
        ContactComment::factory()->count(20)->create();
    }
}
