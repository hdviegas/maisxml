<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When connecting to the production legacy MySQL database these tables already exist;
 * this migration is a no-op. On empty SQLite (tests) it provisions a compatible minimum schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xml')) {
            return;
        }

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('social')->nullable();
            $table->string('fantasia')->nullable();
            $table->string('cnpj', 32)->nullable();
        });

        Schema::create('empresa_filial', function (Blueprint $table) {
            $table->increments('codigo');
            $table->unsignedBigInteger('empresa');
            $table->string('social')->nullable();
            $table->string('fantasia')->nullable();
            $table->string('cnpj', 32)->nullable();
            $table->boolean('entrada')->default(false);
        });

        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->string('login', 64)->index();
            $table->string('senha', 64);
            $table->string('social')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('cnpj', 32)->nullable()->index();
            $table->unsignedTinyInteger('tipo')->default(1);
        });

        Schema::create('xml', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->string('chave', 44)->unique();
            $table->string('empresa', 32)->nullable();
            $table->string('social')->nullable();
            $table->string('dest', 32)->nullable();
            $table->dateTime('data_nf')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('cnpj_emit', 32)->nullable();
            $table->string('cnpj_dest', 32)->nullable();
            $table->string('social_emit')->nullable();
            $table->string('social_dest')->nullable();
            $table->string('numero_nf', 32)->nullable();
            $table->string('modelo_nf', 8)->nullable();
            $table->string('serie_nf', 16)->nullable();
            $table->decimal('total_nf', 15, 2)->nullable();
            $table->decimal('total_icms', 15, 2)->nullable();
            $table->decimal('total_icms_st', 15, 2)->nullable();
            $table->decimal('total_frete', 15, 2)->nullable();
            $table->string('cfop_nf', 16)->nullable();
            $table->boolean('cancelada')->default(false);
            $table->string('sped', 32)->nullable();
        });

        Schema::create('sped', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cnpj', 32)->index();
            $table->date('data_nf')->nullable();
            $table->string('chave_nf', 44)->index();
            $table->decimal('total_nf', 15, 2)->nullable();
            $table->decimal('total_icms', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('xml')) {
            return;
        }

        Schema::dropIfExists('sped');
        Schema::dropIfExists('xml');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('empresa_filial');
        Schema::dropIfExists('empresa');
    }
};
