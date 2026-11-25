<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas SIEG. Sem FK para `empresa` (banco legado, possivelmente MyISAM).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sieg_accounts')) {
            return;
        }

        Schema::create('sieg_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('name', 120);
            $table->text('api_key');
            $table->string('mode', 16)->default('nsu');
            $table->unsignedBigInteger('last_nsu')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('paused_until')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sieg_accounts');
    }
};
