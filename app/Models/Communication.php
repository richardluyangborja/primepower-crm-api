<?php

namespace App\Models;

use App\Enums\CommunicationDirection;
use App\Enums\CommunicationOutcome;
use App\Enums\CommunicationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Communication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'lead_id',
        'client_id',
        'contact_id',
        'user_id',
        'type',
        'direction',
        'outcome',
        'subject',
        'notes',
        'duration_minutes',
        'scheduled_at',
    ];

    protected $casts = [
        'type' => CommunicationType::class,
        'direction' => CommunicationDirection::class,
        'outcome' => CommunicationOutcome::class,
        'scheduled_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
