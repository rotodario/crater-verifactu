<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifactuEventsTable extends Migration
{
    public function up()
    {
        Schema::create('verifactu_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('verifactu_record_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->string('event_code')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id', 'event_type']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('verifactu_record_id')->references('id')->on('verifactu_records')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifactu_events');
    }
}
