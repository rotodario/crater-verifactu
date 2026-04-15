<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifactuSubmission extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'submitted_at'     => 'datetime',
        'completed_at'     => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(VerifactuRecord::class, 'verifactu_record_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
