<?php

test('project task tree item keeps the whole card clickable', function () {
    $componentPath = dirname(__DIR__, 2).'/resources/js/components/ProjectTaskTreeItem.vue';
    $component = file_get_contents($componentPath);

    expect($component)
        ->toContain('@click="visitTask"')
        ->toContain('@keydown.enter.prevent="visitTask"')
        ->toContain('@keydown.space.prevent="visitTask"')
        ->toContain('@click.stop="handleCreateSubtask"')
        ->toContain('@keydown.enter.stop')
        ->toContain('@keydown.space.stop');
});
