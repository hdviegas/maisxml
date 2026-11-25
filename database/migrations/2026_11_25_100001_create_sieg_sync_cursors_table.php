<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sieg_sync_cursors')) {
            return;
        }

        Schema::create('sieg_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sieg_account_id')->index();
            $table->string('cnpj', 14);
            $table->string('role', 4);
            $table->unsignedTinyInteger('tipo_xml');
            $table->dateTime('window_start')->nullable();
            $table->unsignedInteger('skip')->default(0);
            $table->dateTime('synced_until')->nullable();
            $table->timestamps();

            $table->unique(['sieg_account_id', 'cnpj', 'role', 'tipo_xml'], 'sieg_cursor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sieg_sync_cursors');
    }
};
