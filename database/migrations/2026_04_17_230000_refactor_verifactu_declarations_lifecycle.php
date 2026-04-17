<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactors verifactu_declarations to reflect the correct lifecycle of the
 * Declaración Responsable del SIF.
 *
 * El productor certifica esta versión del SIF y la declaración queda
 * incorporada y accesible dentro del propio sistema. No existe flujo de
 * comunicación con la AEAT en este modelo — eso corresponde exclusivamente
 * a los registros de facturación (RegistroAlta / RegistroBaja).
 *
 * NEW states:  DRAFT → GENERATED → REVIEWED → ACTIVE → ARCHIVED
 * OLD states:  DRAFT, SUBMITTED, ACCEPTED, REJECTED
 *
 * State mapping applied during migration:
 *   SUBMITTED → REVIEWED  (estaba lista para validar)
 *   ACCEPTED  → ACTIVE    (estaba vigente)
 *   REJECTED  → DRAFT     (hay que rehacerla)
 *
 * Timestamp mapping:
 *   declared_at → activated_at  (renamed, same semantic)
 *   + generated_at, reviewed_at, archived_at  (new)
 *
 * Data migration:
 *   notes extracted from declaration_payload JSON → own column
 *   declaration_payload remains a pure immutable snapshot of declared SIF data
 */
class RefactorVerifactuDeclarationsLifecycle extends Migration
{
    public function up(): void
    {
        // ── Step 1: add new columns (non-destructive) ──────────────────────
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('generated_at');
            $table->timestamp('activated_at')->nullable()->after('reviewed_at');
            $table->timestamp('archived_at')->nullable()->after('activated_at');
            $table->text('notes')->nullable()->after('archived_at');
        });

        // ── Step 2: migrate declared_at → activated_at ──────────────────────
        DB::statement(
            'UPDATE verifactu_declarations SET activated_at = declared_at WHERE declared_at IS NOT NULL'
        );

        // ── Step 3: extract notes from JSON payload → own column ────────────
        DB::statement("
            UPDATE verifactu_declarations
               SET notes = JSON_UNQUOTE(JSON_EXTRACT(declaration_payload, '$.notes'))
             WHERE JSON_EXTRACT(declaration_payload, '$.notes') IS NOT NULL
        ");

        // ── Step 4: clean notes out of payload (pure snapshot hereafter) ───
        DB::statement("
            UPDATE verifactu_declarations
               SET declaration_payload = JSON_REMOVE(declaration_payload, '$.notes')
             WHERE JSON_EXTRACT(declaration_payload, '$.notes') IS NOT NULL
        ");

        // ── Step 5: map old statuses to new ─────────────────────────────────
        DB::statement("UPDATE verifactu_declarations SET status = 'REVIEWED' WHERE status = 'SUBMITTED'");
        DB::statement("UPDATE verifactu_declarations SET status = 'ACTIVE'   WHERE status = 'ACCEPTED'");
        DB::statement("UPDATE verifactu_declarations SET status = 'DRAFT'    WHERE status = 'REJECTED'");

        // Ensure ACTIVE rows have activated_at even if declared_at was null
        DB::statement("
            UPDATE verifactu_declarations
               SET activated_at = updated_at
             WHERE status = 'ACTIVE' AND activated_at IS NULL
        ");

        // ── Step 6: drop obsolete declared_at ────────────────────────────────
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            $table->dropColumn('declared_at');
        });

        // ── Step 7: narrow ENUM to new valid states ───────────────────────────
        // Blueprint::enum change is unreliable for ENUM narrowing — use raw SQL.
        DB::statement("
            ALTER TABLE verifactu_declarations
            MODIFY COLUMN status ENUM('DRAFT','GENERATED','REVIEWED','ACTIVE','ARCHIVED')
            NOT NULL DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        // Widen ENUM to allow both old and new values for the data migration
        DB::statement("
            ALTER TABLE verifactu_declarations
            MODIFY COLUMN status
            ENUM('DRAFT','GENERATED','REVIEWED','ACTIVE','ARCHIVED','SUBMITTED','ACCEPTED','REJECTED')
            NOT NULL DEFAULT 'DRAFT'
        ");

        // Reverse status mapping (best approximation)
        DB::statement("UPDATE verifactu_declarations SET status = 'SUBMITTED' WHERE status = 'REVIEWED'");
        DB::statement("UPDATE verifactu_declarations SET status = 'ACCEPTED'  WHERE status = 'ACTIVE'");
        DB::statement("UPDATE verifactu_declarations SET status = 'ACCEPTED'  WHERE status = 'ARCHIVED'");
        // DRAFT stays DRAFT

        // Restore ENUM to original values only
        DB::statement("
            ALTER TABLE verifactu_declarations
            MODIFY COLUMN status ENUM('DRAFT','SUBMITTED','ACCEPTED','REJECTED')
            NOT NULL DEFAULT 'DRAFT'
        ");

        // Restore declared_at column
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            $table->timestamp('declared_at')->nullable()->after('status');
        });

        DB::statement(
            'UPDATE verifactu_declarations SET declared_at = activated_at WHERE activated_at IS NOT NULL'
        );

        // Drop new columns
        // Note: notes content extracted from payload is NOT restored to JSON on rollback.
        Schema::table('verifactu_declarations', function (Blueprint $table) {
            $table->dropColumn(['generated_at', 'reviewed_at', 'activated_at', 'archived_at', 'notes']);
        });
    }
}
