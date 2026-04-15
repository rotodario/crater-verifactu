<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifactuInstallation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'submission_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function records()
    {
        return $this->hasMany(VerifactuRecord::class);
    }
}
