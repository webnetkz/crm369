<?php

test('redis queue uses numeric timing values required by phpredis', function () {
    expect(config('queue.connections.redis.block_for'))->toBeInt()->toBe(5)
        ->and(config('queue.connections.redis.retry_after'))->toBeInt();
});
