<?php

test('portal forms page keeps the editor open when switching to create mode', function () {
    $componentPath = dirname(__DIR__, 2).'/resources/js/pages/forms/Index.vue';
    $component = file_get_contents($componentPath);

    expect($component)
        ->toContain('const editorSheetOpen = ref(false);')
        ->toContain('const openingCreateForm = ref(false);')
        ->toContain('syncEditorWithActiveForm(null);')
        ->toContain('preserveState: true')
        ->toContain('openingCreateForm.value = true;')
        ->toContain('onFinish: () => {')
        ->toContain('editorSheetOpen.value = true;');
});

test('portal forms page opens the sidebar only after selecting a form', function () {
    $componentPath = dirname(__DIR__, 2).'/resources/js/pages/forms/Index.vue';
    $component = file_get_contents($componentPath);

    expect($component)
        ->toContain('role="button"')
        ->toContain('@click="openForm(portalForm.id)"')
        ->toContain('@keydown.enter.prevent="openForm(portalForm.id)"')
        ->toContain('@keydown.space.prevent="openForm(portalForm.id)"')
        ->toContain('@click.stop="deleteForm(portalForm.id)"');
});

test('portal forms page shows form info only in the sidebar while editing', function () {
    $componentPath = dirname(__DIR__, 2).'/resources/js/pages/forms/Index.vue';
    $component = file_get_contents($componentPath);

    expect($component)
        ->toContain('<aside class="space-y-4">')
        ->and(substr_count($component, 't.forms.owner'))->toBe(1)
        ->and(substr_count($component, 't.forms.delivery_target'))->toBe(2)
        ->and($component)->not->toContain('class="grid gap-3 md:grid-cols-2"');
});
