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
            // Mirrors production `xml` (MySQL 5.7 strict): NOT NULL columns have no implicit default.
            $table->id();
            $table->string('url', 500);
            $table->timestamp('data_up')->useCurrent();
            $table->string('empresa', 20);
            $table->string('social', 200);
            $table->string('chave', 44)->unique();
            $table->bigInteger('downs')->nullable()->default(0);
            $table->string('dest', 30);
            $table->date('data_nf')->nullable();
            $table->string('ip', 40);
            $table->date('sped')->nullable();
            $table->integer('cancelada')->default(0);
            $table->string('social_dest', 100)->nullable();
            $table->string('social_emit', 100)->nullable();
            $table->string('cnpj_emit', 20)->nullable();
            $table->string('cnpj_dest', 20)->nullable();
            $table->string('manifestado_dest', 10)->nullable();
            $table->string('tipo', 5);
            $table->integer('numero_nf');
            $table->integer('modelo_nf')->nullable();
            $table->float('total_nf');
            $table->float('total_icms');
            $table->float('total_icms_st')->nullable();
            $table->float('total_frete');
            $table->string('serie_nf', 5)->nullable();
            $table->string('cfop_nf', 4)->nullable();
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
