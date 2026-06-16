<?php

namespace App\Contracts;

interface NotificationProviderInterface {
    public function send(string $recipient, string $message): array; // Возвращает ['status' => 'success'|'error', 'message' => '...']
}
