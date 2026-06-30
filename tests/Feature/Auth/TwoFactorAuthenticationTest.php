<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('two factor qr code url uses configured issuer name', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config()->set('app.name', 'Laravel');
    config()->set('app.two_factor_issuer', 'CRM369');

    $user = User::factory()->withTwoFactor()->create([
        'email' => 'roman@example.com',
    ]);

    $url = $user->twoFactorQrCodeUrl();

    expect($url)
        ->toContain('otpauth://totp/CRM369:roman%40example.com')
        ->toContain('issuer=CRM369');
});
