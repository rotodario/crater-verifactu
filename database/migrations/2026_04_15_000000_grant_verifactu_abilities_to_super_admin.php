<?php

use Crater\Models\Company;
use Illuminate\Database\Migrations\Migration;

class GrantVerifactuAbilitiesToSuperAdmin extends Migration
{
    public function up()
    {
        Company::each(function (Company $company) {
            $company->setupRoles();
        });
    }

    public function down()
    {
        // Abilities are additive; no rollback needed.
    }
}
