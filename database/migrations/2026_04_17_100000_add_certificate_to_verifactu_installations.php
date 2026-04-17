<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCertificateToVerifactuInstallations extends Migration
{
    public function up()
    {
        Schema::table('verifactu_installations', function (Blueprint $table) {
            $table->longText('cert_data')->nullable()->after('settings');     // encrypted raw bytes
            $table->text('cert_password')->nullable()->after('cert_data');    // encrypted password
            $table->string('cert_filename')->nullable()->after('cert_password');
            $table->string('cert_type', 10)->nullable()->after('cert_filename'); // p12 | pem
        });
    }

    public function down()
    {
        Schema::table('verifactu_installations', function (Blueprint $table) {
            $table->dropColumn(['cert_data', 'cert_password', 'cert_filename', 'cert_type']);
        });
    }
}
