<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->comment('Для идемпотентности');
            $table->string('recipient_id')->comment('ID пользователя/телефона/email');
            $table->enum('channel', ['sms', 'email']);
            $table->text('message');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->enum('priority', ['transactional', 'marketing'])->default('marketing');
            $table->string('provider_response')->nullable();
            $table->timestamps();

            // Гарантированная идемпотентность на уровне БД
            $table->unique(['request_id', 'recipient_id'], 'uniq_request_recipient');
            $table->index(['recipient_id', 'status']);
        });
    }
};
