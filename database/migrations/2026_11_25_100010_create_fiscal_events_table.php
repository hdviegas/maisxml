<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fiscal_events')) {
            return;
        }

        Schema::create('fiscal_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('chave', 44)->index();
            $table->unsignedInteger('tp_evento');
            $table->unsignedSmallInteger('n_seq_evento')->default(1);
            $table->dateTime('dh_evento')->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->string('descricao', 120)->nullable();
            $table->text('x_correcao')->nullable();
            $table->string('storage_path');
            $table->string('source', 16)->default('sieg');
            $table->timestamps();

            $table->unique(['chave', 'tp_evento', 'n_seq_evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_events');
    }
};
