<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContactAvatarManager
{
    public function sync(Contact $contact, ?UploadedFile $avatar): bool
    {
        if (! $avatar instanceof UploadedFile) {
            return false;
        }

        $previousAvatarPath = $contact->avatar_path;
        $contact->avatar_path = $avatar->store('contacts/'.$contact->id, 'public');

        if ($previousAvatarPath && $previousAvatarPath !== $contact->avatar_path) {
            Storage::disk('public')->delete($previousAvatarPath);
        }

        return true;
    }
}
