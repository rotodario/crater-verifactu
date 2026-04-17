<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds optional extended fields to verifactu_platform_config needed to
 * generate a complete Declaración Responsable del SIF document.
 *
 * All fields are nullable — if absent, the PDF shows them as pending.
 */
class AddExtendedFieldsToVerifactuPlatformConfig extends Migration
{
    public function up(): void
    {
        Schema::table('verifactu_platform_config', function (Blueprint $table) {
            $table->string('vendor_address', 255)->nullable()->after('vendor_tax_id');
            $table->text('vendor_description')->nullable()->after('vendor_address');
            $table->string('subscription_place', 120)->nullable()->after('vendor_description');
        });
    }

    public function down(): void
    {
        Schema::table('verifactu_platform_config', function (Blueprint $table) {
            $table->dropColumn(['vendor_address', 'vendor_description', 'subscription_place']);
        });
    }
}
