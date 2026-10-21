<?php

namespace App\Http\Controllers;

use App\Support\MobileWebSessionBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileWebSessionLoginController extends Controller
{
    /**
     * Sign the native application user into the web application and open the requested page.
     */
    public function __invoke(Request $request, string $token, MobileWebSessionBridge $mobileWebSessionBridge): RedirectResponse
    {
        $consumed = $mobileWebSessionBridge->consume($token);

        if ($consumed === null) {
            return redirect()->route('home');
        }

        if (! Auth::guard('web')->check() || Auth::guard('web')->id() !== $consumed['user']->id) {
            Auth::guard('web')->login($consumed['user']);
            $request->session()->regenerate();
        }

        return redirect()->to($consumed['path']);
    }
}
