<?php

test('knowledge article editor does not auto-collapse the sidebar', function () {
    $source = file_get_contents(resource_path('js/pages/knowledge/Index.vue'));

    expect($source)
        ->toBeString()
        ->not->toContain('sidebarCollapsed:');
});
