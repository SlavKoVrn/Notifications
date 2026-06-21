<?php

namespace App\Jobs;

use App\Providers\MockSmsProvider;
use App\Models\Notification;
use App\Services\IdempotencyService;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessNotificationJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Экспоненциальная задержка

    public function __construct(public int $notificationId) {}

    public function handle() {
        $notification = Notification::find($this->notificationId);
        if (!$notification) return;

        $provider = new MockSmsProvider;

        // 1. Проверка идемпотентности (защита от повторной обработки при retry)
        /*
        if ($idempotency->isProcessed($notification->request_id, $notification->recipient_id)) {
            Log::info("Notification already processed (idempotency hit)", ['id' => $notification->id]);
            return;
        }
        */

        DB::beginTransaction();
        try {
            // 2. Статус "Отправлено" (передано шлюзу)
            $notification->status = 'sent';
            $notification->save();

            // 3. Вызов провайдера
            $response = $provider->send($notification->recipient_id, $notification->message);

            if ($response['status'] === 'success') {
                $notification->status = 'delivered';
                //$idempotency->markAsProcessed($notification->request_id, $notification->recipient_id);
            } else {
                $notification->status = 'failed';
                $notification->provider_response = $response['message'];
                // Не помечаем как processed, если это постоянная ошибка, чтобы не спамить,
                // но и не retry-им, если ошибка фатальная (можно добавить логику классификации ошибок)
            }

            $notification->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Notification job failed", ['id' => $this->notificationId, 'error' => $e->getMessage()]);
            throw $e; // Вернет джобу в очередь для retry согласно $backoff
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception, $payload = null)
    {
        // $payload contains the serialized data of the job
        Log::error("Notification id {$this->notificationId} failed permanently.", [
            'exception' => $exception->getMessage(),
            'payload' => $payload
        ]);
    }

}
