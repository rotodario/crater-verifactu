<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoFacturaToVerifactuRecords extends Migration
{
    public function up()
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            // AEAT TipoFactura code: F1, F2, R1-R5
            $table->string('tipo_factura', 2)->default('F1')->after('invoice_date');
        });
    }

    public function down()
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            $table->dropColumn('tipo_factura');
        });
    }
}
