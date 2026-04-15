<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifactuInstallationsTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_installations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->string('mode')->default('shadow');
            $table->boolean('enabled')->default(true);
            $table->boolean('submission_enabled')->default(false);
            $table->string('environment')->default('local');
            $table->string('issuer_name')->nullable();
            $table->string('issuer_tax_id')->nullable();
            $table->string('software_name')->nullable();
            $table->string('software_version')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_installations');
    }
}
