<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Declaración Responsable belongs to the SOFTWARE PLATFORM, not to individual
 * client companies. Making company_id nullable allows platform-level declarations
 * (company_id = NULL) while preserving any existing per-company rows.
 */
class MakeDeclarationsCompanyIdNullable extends Migration
{
    public function up()
    {
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            // Drop foreign key first, then modify, then re-add as nullable
            $table->dropForeign(['company_id']);
            $table->unsignedInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->unsignedInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
}
