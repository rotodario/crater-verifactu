<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifactuInstallation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'enabled'            => 'boolean',
        'submission_enabled' => 'boolean',
        'settings'           => 'array',
        'cert_data'          => 'encrypted',
        'cert_password'      => 'encrypted',
    ];

    public function hasCertificate(): bool
    {
        return ! empty($this->cert_data);
    }

    public function getCertBytes(): ?string
    {
        return $this->cert_data ?: null;
    }

    public function getCertPassword(): string
    {
        return $this->cert_password ?? '';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function records()
    {
        return $this->hasMany(VerifactuRecord::class);
    }
}
