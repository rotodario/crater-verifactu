<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifactuRecordsTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('invoice_id');
            $table->unsignedBigInteger('verifactu_installation_id')->nullable();
            $table->string('record_type')->default('invoice_registration');
            $table->string('status')->default('ISSUED');
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->string('invoice_uid');
            $table->string('hash');
            $table->string('previous_hash')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('locked_at')->nullable();
            $table->json('snapshot');
            $table->json('qr_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id']);
            $table->unique('invoice_uid');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('verifactu_installation_id')->references('id')->on('verifactu_installations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_records');
    }
}
