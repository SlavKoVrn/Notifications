<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class IdempotencyService {
    public function isProcessed(string $requestId, string $recipientId): bool {
        $key = "notif_processed:{$requestId}:{$recipientId}";
        return (bool) Redis::get($key);
    }

    public function markAsProcessed(string $requestId, string $recipientId, int $ttl = 86400): void {
        $key = "notif_processed:{$requestId}:{$recipientId}";
        Redis::setex($key, $ttl, '1');
    }
}
