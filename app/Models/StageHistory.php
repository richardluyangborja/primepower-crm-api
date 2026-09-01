<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageHistory extends Model
{
    protected $fillable = [
        'opportunity_id',
        'user_id',
        'from_stage',
        'to_stage',
        'reason',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
