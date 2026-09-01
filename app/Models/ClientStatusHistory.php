<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientStatusHistory extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'from_status',
        'to_status',
        'reason',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
