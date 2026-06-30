<?php

test('standalone crm369 landing assets exist and are wired together', function () {
    $landingDirectory = public_path('landing-crm369');
    $indexPath = $landingDirectory.'/index.html';
    $stylesPath = $landingDirectory.'/styles.css';
    $scriptPath = $landingDirectory.'/script.js';
    $logoPath = $landingDirectory.'/logo.png';

    expect(is_dir($landingDirectory))->toBeTrue()
        ->and(file_exists($indexPath))->toBeTrue()
        ->and(file_exists($stylesPath))->toBeTrue()
        ->and(file_exists($scriptPath))->toBeTrue()
        ->and(file_exists($logoPath))->toBeTrue();

    $index = file_get_contents($indexPath);
    $styles = file_get_contents($stylesPath);
    $script = file_get_contents($scriptPath);

    expect($index)->toContain('CRM369 | Лендинг')
        ->toContain('styles.css')
        ->toContain('script.js')
        ->toContain('logo.png')
        ->toContain('<link rel="icon" href="./logo.png" type="image/png" />')
        ->toContain('fonts.googleapis.com')
        ->toContain('family=Ubuntu')
        ->toContain('https://wa.me/77078453424')
        ->toContain('+77078453424')
        ->toContain('topbar__phone')
        ->toContain('id="modules"')
        ->toContain('id="flow"')
        ->toContain('id="control"')
        ->toContain('Воронки')
        ->toContain('Публичные формы')
        ->and($styles)->toContain('.hero')
        ->toContain('.brandmark__logo')
        ->toContain('font-family:')
        ->toContain('Ubuntu')
        ->toContain('.whatsapp-float')
        ->toContain('.topbar__phone')
        ->toContain('.modules-grid')
        ->toContain('.cta-box')
        ->and($script)->toContain('IntersectionObserver')
        ->toContain('pointermove');

    expect($index)->not->toContain('Контакт для связи')
        ->not->toContain('class="contact-line"');
});
