<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaxNumberToCompaniesAndCustomers extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('company_name');
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });
    }
}
