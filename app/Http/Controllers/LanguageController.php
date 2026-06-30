<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLanguageRequest;
use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    public function update(UpdateLanguageRequest $request): RedirectResponse
    {
        $request->user()->update([
            'language' => $request->language(),
            'has_selected_language' => true,
        ]);

        return back()->withCookie(cookie()->forever('language', $request->language()));
    }
}
