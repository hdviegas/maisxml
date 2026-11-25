<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sieg_sync_cursors') || Schema::hasColumn('sieg_sync_cursors', 'window_end')) {
            return;
        }

        Schema::table('sieg_sync_cursors', function (Blueprint $table) {
            $table->dateTime('window_end')->nullable()->after('window_start');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sieg_sync_cursors', 'window_end')) {
            Schema::table('sieg_sync_cursors', function (Blueprint $table) {
                $table->dropColumn('window_end');
            });
        }
    }
};
