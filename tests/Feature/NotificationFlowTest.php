<?php

namespace Tests\Feature;

use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationFlowTest extends TestCase {
    use RefreshDatabase;

    public function test_bulk_notification_dispatch_and_processing() {
        Queue::fake(); // Фейкаем очередь для проверки диспатча

        $payload = [
            'request_id' => 'req-123',
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Your code is 1234',
            'recipients' => ['+79001112233', '+79004445566']
        ];

        $response = $this->postJson('/api/v1/notifications/bulk', $payload);

        $response->assertStatus(202)
                 ->assertJson(['accepted' => 2]);

        // Проверяем, что джобы отправлены в правильную очередь
        Queue::assertPushedOn('notifications_transactional', ProcessNotificationJob::class);

        // Проверяем, что джоба была отправлена ровно 2 раза
        Queue::assertPushed(ProcessNotificationJob::class, 2);

        // Проверяем запись в БД
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => '+79001112233',
            'status' => 'queued',
            'request_id' => 'req-123'
        ]);
    }

    public function test_idempotency_prevents_duplicate_processing() {
        $notification = Notification::create([
            'request_id' => 'req-dup',
            'recipient_id' => '+79999999999',
            'channel' => 'email',
            'message' => 'Hello',
            'status' => 'queued',
            'priority' => 'marketing'
        ]);

        $idempotencyService = app(IdempotencyService::class);
        $idempotencyService->markAsProcessed('req-dup', '+79999999999');

        // Запускаем джобу вручную
        $job = new ProcessNotificationJob($notification->id);
        $job->handle(app(\App\Providers\MockSmsProvider::class), $idempotencyService);

        // Статус не должен был измениться на 'sent', так как сработала защита
        $notification->refresh();
        $this->assertEquals('queued', $notification->status);
    }

    public function test_recipient_notification_history() {
        $recipientId = '+79001234567';

        // 1. Создаем уведомления для целевого получателя
        Notification::create([
            'request_id' => 'req-hist-1',
            'recipient_id' => $recipientId,
            'channel' => 'sms',
            'message' => 'Test message 1',
            'status' => 'sent',
            'priority' => 'transactional'
        ]);

        Notification::create([
            'request_id' => 'req-hist-2',
            'recipient_id' => $recipientId,
            'channel' => 'email',
            'message' => 'Test message 2',
            'status' => 'delivered',
            'priority' => 'marketing'
        ]);

        // 2. Создаем уведомление для другого получателя (оно не должно попасть в выдачу)
        Notification::create([
            'request_id' => 'req-hist-3',
            'recipient_id' => '+79009999999',
            'channel' => 'sms',
            'message' => 'Other user message',
            'status' => 'queued',
            'priority' => 'transactional'
        ]);

        // 3. Выполняем GET запрос к эндпоинту
        $response = $this->getJson("/api/v1/notifications/{$recipientId}/history");

        // 4. Проверяем HTTP статус и структуру ответа согласно OpenAPI спецификации
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'channel',
                             'status',
                             'priority',
                             'created_at'
                         ]
                     ]
                 ]);

        // 5. Проверяем количество записей (должно вернуться ровно 2)
        $response->assertJsonCount(2, 'data');

        // 6. Проверяем, что в выдачу попали правильные уведомления с нужными данными
        $response->assertJsonFragment([
            'channel' => 'sms',
            'status' => 'sent',
            'priority' => 'transactional'
        ]);

        $response->assertJsonFragment([
            'channel' => 'email',
            'status' => 'delivered',
            'priority' => 'marketing'
        ]);
    }

}
