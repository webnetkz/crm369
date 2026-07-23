<?php

namespace App\Support\SystemUpdates;

class SystemUpdateBridgeInspector
{
    public function isAvailable(): bool
    {
        $path = (string) config('system-updates.bridge_path');

        if ($path === '') {
            return false;
        }

        $metadata = @lstat($path);

        if (! is_array($metadata)) {
            return false;
        }

        return $this->metadataIsSecure(
            owner: (int) $metadata['uid'],
            permissions: (int) $metadata['mode'],
        );
    }

    public function metadataIsSecure(int $owner, int $permissions): bool
    {
        return ($permissions & 0170000) === 0100000
            && $owner === 0
            && ($permissions & 0100) !== 0
            && ($permissions & 0022) === 0;
    }
}
