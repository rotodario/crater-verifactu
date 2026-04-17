<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifactuDeclaration extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'declaration_payload' => 'array',
        'generated_at'        => 'datetime',
        'reviewed_at'         => 'datetime',
        'activated_at'        => 'datetime',
        'archived_at'         => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Returns true if this declaration is the current active one for the SIF.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
