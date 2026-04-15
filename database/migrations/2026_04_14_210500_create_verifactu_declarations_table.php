<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifactuDeclarationsTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_declarations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->string('software_name');
            $table->string('software_version');
            $table->string('status')->default('DRAFT');
            $table->json('declaration_payload')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'software_version']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_declarations');
    }
}
