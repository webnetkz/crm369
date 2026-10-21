<?php

namespace App\Support;

use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NewsManager
{
    /**
     * Create a news item, storing the uploaded cover image on the public disk.
     */
    public function create(StoreNewsRequest $request, User $user): News
    {
        return News::query()->create([
            ...$request->payload(),
            'image_path' => $this->storeUploadedImage($request),
            'published_at' => $request->boolean('is_published') ? now() : null,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
    }

    /**
     * Update a news item, replacing or removing its cover image and deleting the previous file when it changes.
     */
    public function update(StoreNewsRequest $request, News $news, ?User $user): News
    {
        $currentImagePath = $news->image_path;
        $nextImagePath = $currentImagePath;

        if ($request->boolean('remove_image')) {
            $nextImagePath = null;
        }

        $uploadedImagePath = $this->storeUploadedImage($request);

        if ($uploadedImagePath !== null) {
            $nextImagePath = $uploadedImagePath;
        }

        $news->update([
            ...$request->payload(),
            'image_path' => $nextImagePath,
            'published_at' => $request->boolean('is_published')
                ? ($news->published_at ?? now())
                : null,
            'updated_by_user_id' => $user?->id,
        ]);

        if (
            is_string($currentImagePath)
            && $currentImagePath !== ''
            && $currentImagePath !== $nextImagePath
        ) {
            Storage::disk('public')->delete($currentImagePath);
        }

        return $news;
    }

    /**
     * Delete a news item together with its cover image.
     */
    public function delete(News $news): void
    {
        $imagePath = $news->image_path;
        $news->delete();

        if (is_string($imagePath) && $imagePath !== '') {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function storeUploadedImage(StoreNewsRequest $request): string|false|null
    {
        $imageFile = $request->file('image_file');

        return $imageFile instanceof UploadedFile
            ? $imageFile->store('news', 'public')
            : null;
    }
}
