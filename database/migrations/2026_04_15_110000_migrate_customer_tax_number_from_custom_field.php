<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MigrateCustomerTaxNumberFromCustomField extends Migration
{
    /**
     * Custom field slug used to store the fiscal code in the old setup.
     */
    const CUSTOM_FIELD_SLUG = 'CUSTOM_CUSTOMER_CODIGO_FISCAL';

    public function up()
    {
        $customField = DB::table('custom_fields')
            ->where('slug', self::CUSTOM_FIELD_SLUG)
            ->first();

        if (! $customField) {
            return;
        }

        $values = DB::table('custom_field_values')
            ->where('custom_field_id', $customField->id)
            ->where('custom_field_valuable_type', 'Crater\\Models\\Customer')
            ->whereNotNull('string_answer')
            ->where('string_answer', '!=', '')
            ->get();

        foreach ($values as $value) {
            DB::table('customers')
                ->where('id', $value->custom_field_valuable_id)
                ->whereNull('tax_number')
                ->update(['tax_number' => trim($value->string_answer)]);
        }
    }

    public function down()
    {
        // Non-destructive: tax_number column drop is handled by its own migration.
    }
}
