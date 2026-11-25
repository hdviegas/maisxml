<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sieg_sync_runs')) {
            return;
        }

        Schema::create('sieg_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sieg_account_id')->index();
            $table->string('mode', 16);
            $table->string('status', 16);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            foreach (['requests', 'downloaded', 'imported', 'duplicates', 'ignored', 'events', 'errors'] as $c) {
                $table->unsignedInteger($c)->default(0);
            }
            $table->unsignedBigInteger('from_nsu')->nullable();
            $table->unsignedBigInteger('to_nsu')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sieg_sync_runs');
    }
};
