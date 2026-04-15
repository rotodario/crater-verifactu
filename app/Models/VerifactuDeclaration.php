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
        'declared_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
