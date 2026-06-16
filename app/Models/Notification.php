<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model {
    protected $fillable = [
        'request_id', 'recipient_id', 'channel', 'message', 
        'status', 'priority', 'provider_response'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
