<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "provision legacy schema" migration only runs when `xml` is absent.
 * Some databases already have `xml` but no `usuario`; this migration fixes that.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('legacy.usuario_table', 'usuario');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('login', 64)->index();
            $table->string('senha', 64);
            $table->string('social')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('cnpj', 32)->nullable()->index();
            $table->unsignedTinyInteger('tipo')->default(1);
        });
    }

    public function down(): void
    {
        $tableName = (string) config('legacy.usuario_table', 'usuario');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::drop($tableName);
    }
};
