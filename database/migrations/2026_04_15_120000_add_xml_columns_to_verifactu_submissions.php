<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddXmlColumnsToVerifactuSubmissions extends Migration
{
    public function up()
    {
        Schema::table('verifactu_submissions', function (Blueprint $table) {
            // Raw SOAP XML exchanged with AEAT
            $table->longText('request_xml')->nullable()->after('request_payload');
            $table->longText('response_xml')->nullable()->after('response_payload');
            // CSV = Código Seguro de Verificación returned by AEAT on success
            $table->string('csv', 40)->nullable()->after('external_reference');
        });
    }

    public function down()
    {
        Schema::table('verifactu_submissions', function (Blueprint $table) {
            $table->dropColumn(['request_xml', 'response_xml', 'csv']);
        });
    }
}
