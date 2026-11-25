<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réparation non-destructive du schéma MySQL existant et ajout des colonnes/tables manquantes.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            });
        }

        if (Schema::hasTable('justificatifs') && !Schema::hasColumn('justificatifs', 'commentaire_stagiaire')) {
            Schema::table('justificatifs', function (Blueprint $table) {
                $table->text('commentaire_stagiaire')->nullable()->after('motif_decision');
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedSmallInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('connection');
                $table->string('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();

                $table->index(['connection', 'queue', 'failed_at']);
            });
        }

        // Corriger la contrainte CHECK (<= 2.50 -> <= 20.00) et le DEFAULT sur stagiaires.note_discipline sous MySQL
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('stagiaires')) {
            $dbName = DB::getDatabaseName();

            $legacyCheck = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stagiaires'
                 AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'stagiaires_chk_1'",
                [$dbName]
            );

            if ($legacyCheck) {
                DB::statement('ALTER TABLE `stagiaires` DROP CHECK `stagiaires_chk_1`');
            }

            DB::statement('ALTER TABLE `stagiaires` MODIFY `note_discipline` DECIMAL(4,2) NOT NULL DEFAULT 20.00');

            $newCheck = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stagiaires'
                 AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'stagiaires_note_check'",
                [$dbName]
            );

            if (!$newCheck) {
                DB::statement('ALTER TABLE `stagiaires` ADD CONSTRAINT `stagiaires_note_check` CHECK (`note_discipline` >= 0.00 AND `note_discipline` <= 20.00)');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('justificatifs') && Schema::hasColumn('justificatifs', 'commentaire_stagiaire')) {
            Schema::table('justificatifs', function (Blueprint $table) {
                $table->dropColumn('commentaire_stagiaire');
            });
        }
    }
};
