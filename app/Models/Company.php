<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'industry',
        'address',
        'phone',
        'email',
        'website',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(Contact::class)
            ->where('is_primary', true);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class)->latest();
    }
}
