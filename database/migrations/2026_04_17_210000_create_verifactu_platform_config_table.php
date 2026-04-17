<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a single-row global table for the platform SIF (Software de Información Fiscal)
 * identity that is shared across all companies.
 *
 * Also removes the per-installation SIF columns added in the previous migration,
 * since IdSistemaInformatico, vendor NIF/name belong to the SOFTWARE DEVELOPER,
 * not to individual client companies.
 */
class CreateVerifactuPlatformConfigTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_platform_config', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->default(1)->primary(); // always row id=1
            // Software identification fields sent in every SistemaInformatico XML block
            $table->string('software_name')->nullable();
            $table->string('software_version')->nullable();
            $table->string('vendor_name')->nullable();     // NombreRazon del desarrollador
            $table->string('vendor_tax_id')->nullable();   // NIF del desarrollador
            $table->string('software_id')->nullable();     // IdSistemaInformatico (AEAT)
            $table->timestamps();
        });

        // Remove per-company SIF fields — they do not belong to individual installations
        Schema::table('verifactu_installations', function (Blueprint $table) {
            $table->dropColumn(['vendor_name', 'vendor_tax_id', 'software_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_platform_config');

        Schema::table('verifactu_installations', function (Blueprint $table) {
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tax_id')->nullable();
            $table->string('software_id')->nullable();
        });
    }
}
