<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row global configuration for the platform SIF (Sistema de Información Fiscal).
 *
 * IdSistemaInformatico and vendor identity belong to the SOFTWARE DEVELOPER, not to
 * individual client companies. This record is shared across all company installations.
 *
 * Usage:
 *   $config = VerifactuPlatformConfig::current();
 *   $config->software_id;   // IdSistemaInformatico
 */
class VerifactuPlatformConfig extends Model
{
    protected $table = 'verifactu_platform_config';

    protected $guarded = [];

    /**
     * Always return the single platform config row, pre-populated from env/config
     * when it does not exist yet.
     */
    public static function current(): self
    {
        return static::firstOrNew(['id' => 1], [
            'software_name'   => config('verifactu.software.name'),
            'software_version'=> config('verifactu.software.version'),
            'vendor_name'     => config('verifactu.software.vendor_name'),
            'vendor_tax_id'   => config('verifactu.software.vendor_tax_id'),
            'software_id'     => config('verifactu.software.id'),
        ]);
    }

    /**
     * Upsert the single platform config row.
     */
    public static function persist(array $data): self
    {
        $config = static::firstOrNew(['id' => 1]);
        $config->fill($data);
        $config->id = 1;
        $config->save();
        return $config;
    }
}
