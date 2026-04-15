<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVerifactuFieldsToInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fiscal_status')->default('NOT_ISSUED')->after('paid_status');
            $table->timestamp('fiscal_issued_at')->nullable()->after('fiscal_status');
            $table->timestamp('fiscal_locked_at')->nullable()->after('fiscal_issued_at');
            $table->unsignedBigInteger('verifactu_record_id')->nullable()->after('fiscal_locked_at');
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_status',
                'fiscal_issued_at',
                'fiscal_locked_at',
                'verifactu_record_id',
            ]);
        });
    }
}
