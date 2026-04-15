<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifactuSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('verifactu_record_id');
            $table->unsignedInteger('company_id');
            $table->string('driver')->default('stub');
            $table->string('status')->default('PENDING');
            $table->unsignedInteger('attempt')->default(1);
            $table->string('external_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['verifactu_record_id', 'status']);
            $table->foreign('verifactu_record_id')->references('id')->on('verifactu_records')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_submissions');
    }
}
