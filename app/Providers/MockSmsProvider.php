<?php

namespace App\Providers;

use App\Contracts\NotificationProviderInterface;
use Illuminate\Support\Facades\Log;

class MockSmsProvider implements NotificationProviderInterface {
    public function send(string $recipient, string $message): array {
        Log::info("Mock SMS sending to {$recipient}: {$message}");
        
        // Имитация временной недоступности (10% шанс) для проверки retry-механизма
        if (rand(1, 10) === 1) {
            throw new \Exception("Temporary gateway timeout");
        }

        // Имитация ошибки доставки (несуществующий номер)
        if (str_starts_with($recipient, '000')) {
            return ['status' => 'failed', 'message' => 'Invalid number'];
        }

        return ['status' => 'success', 'message' => 'Delivered to gateway'];
    }
}
