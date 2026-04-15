<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRectificationFieldsToInvoicesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('invoices', 'invoice_kind')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_kind')->default('STANDARD')->after('template_name');
            });
        }

        if (! Schema::hasColumn('invoices', 'original_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedInteger('original_invoice_id')->nullable()->after('invoice_kind');
            });
        }

        $column = DB::selectOne("SHOW COLUMNS FROM invoices WHERE Field = 'original_invoice_id'");
        if ($column && stripos($column->Type, 'bigint') !== false) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedInteger('original_invoice_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('invoices', 'rectification_type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('rectification_type')->nullable()->after('original_invoice_id');
            });
        }

        if (! Schema::hasColumn('invoices', 'rectification_reason')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('rectification_reason')->nullable()->after('rectification_type');
            });
        }

        $database = DB::getDatabaseName();
        $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'invoices')
            ->where('COLUMN_NAME', 'original_invoice_id')
            ->where('REFERENCED_TABLE_NAME', 'invoices')
            ->exists();

        if (! $foreignKeyExists) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('original_invoice_id')->references('id')->on('invoices')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        $database = DB::getDatabaseName();
        $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'invoices')
            ->where('COLUMN_NAME', 'original_invoice_id')
            ->where('REFERENCED_TABLE_NAME', 'invoices')
            ->exists();

        Schema::table('invoices', function (Blueprint $table) use ($foreignKeyExists) {
            if ($foreignKeyExists) {
                $table->dropForeign(['original_invoice_id']);
            }
        });

        $columns = array_filter([
            Schema::hasColumn('invoices', 'invoice_kind') ? 'invoice_kind' : null,
            Schema::hasColumn('invoices', 'original_invoice_id') ? 'original_invoice_id' : null,
            Schema::hasColumn('invoices', 'rectification_type') ? 'rectification_type' : null,
            Schema::hasColumn('invoices', 'rectification_reason') ? 'rectification_reason' : null,
        ]);

        if (! empty($columns)) {
            Schema::table('invoices', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
}
