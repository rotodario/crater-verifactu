<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftwareIdentityToVerifactuInstallations extends Migration
{
    public function up()
    {
        Schema::table('verifactu_installations', function (Blueprint $table) {
            // Developer / vendor that built the SIF (Sistema de Información Fiscal)
            $table->string('vendor_name')->nullable()->after('software_version');
            $table->string('vendor_tax_id')->nullable()->after('vendor_name');

            // IdSistemaInformatico: assigned by AEAT when the software is registered.
            // Mandatory for aeat_sandbox and aeat_production modes.
            $table->string('software_id')->nullable()->after('vendor_tax_id');

            // NumeroInstalacion: identifies this specific deployment instance.
            $table->string('installation_number')->default('1')->after('software_id');
        });
    }

    public function down()
    {
        Schema::table('verifactu_installations', function (Blueprint $table) {
            $table->dropColumn(['vendor_name', 'vendor_tax_id', 'software_id', 'installation_number']);
        });
    }
}
