<?php

namespace App\Http\Responses\Auth;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class MobileAppLoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        if (str_contains((string) $request->userAgent(), 'CRM369MobileApp')) {
            return redirect()->to(Fortify::redirects('login'));
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Fortify::redirects('login'));
    }
}
