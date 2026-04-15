<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifactuRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
        'qr_payload' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installation()
    {
        return $this->belongsTo(VerifactuInstallation::class, 'verifactu_installation_id');
    }

    public function submissions()
    {
        return $this->hasMany(VerifactuSubmission::class);
    }

    public function events()
    {
        return $this->hasMany(VerifactuEvent::class);
    }
}
