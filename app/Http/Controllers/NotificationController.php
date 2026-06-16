<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller {
    
    // POST /api/v1/notifications/bulk
    public function sendBulk(Request $request) {
        $validated = $request->validate([
            'request_id' => 'required|string|max:255',
            'channel' => 'required|in:sms,email',
            'priority' => 'required|in:transactional,marketing',
            'message' => 'required|string',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
        ]);

        $queueName = $validated['priority'] === 'transactional' 
            ? 'notifications_transactional' 
            : 'notifications_marketing';

        $createdCount = 0;

        // Используем транзакцию для атомарного создания записей
        DB::transaction(function () use ($validated, $queueName, &$createdCount) {
            foreach ($validated['recipients'] as $recipient) {
                // Попытка вставки. Если request_id + recipient уже есть, 
                // уникальный индекс БД предотвратит дублирование (идемпотентность)
                try {
                    $notification = Notification::create([
                        'request_id' => $validated['request_id'],
                        'recipient_id' => $recipient,
                        'channel' => $validated['channel'],
                        'message' => $validated['message'],
                        'priority' => $validated['priority'],
                        'status' => 'queued',
                    ]);

                    ProcessNotificationJob::dispatch($notification->id)->onQueue($queueName);
                    $createdCount++;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23505') { // PostgreSQL unique violation
                        continue; // Игнорируем дубликаты в рамках одного bulk-запроса
                    }
                    throw $e;
                }
            }
        });

        return response()->json([
            'message' => 'Bulk dispatch initiated',
            'accepted' => $createdCount,
            'total_requested' => count($validated['recipients'])
        ], 202);
    }

    // GET /api/v1/notifications/{recipient_id}/history
    public function getHistory(string $recipientId) {
        $notifications = Notification::where('recipient_id', $recipientId)
            ->orderByDesc('created_at')
            ->get(['id', 'channel', 'message', 'status', 'priority', 'created_at', 'updated_at']);

        return response()->json(['data' => $notifications]);
    }
}
