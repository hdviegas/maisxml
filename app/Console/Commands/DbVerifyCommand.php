<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DbVerifyCommand extends Command
{
    protected $signature = 'db:verify';

    protected $description = 'Confirm DB credentials work and the legacy usuario table is reachable';

    public function handle(): int
    {
        $name = (string) config('database.default');
        $cfg = config("database.connections.{$name}");
        if (! is_array($cfg)) {
            $this->error('No database connection config.');

            return self::FAILURE;
        }

        $this->info("Connection: {$name} ({$cfg['driver']})");
        $this->line('Host: '.($cfg['host'] ?? '—'));
        $this->line('Database: '.($cfg['database'] ?? '—'));
        $this->line('Username: '.($cfg['username'] ?? '—'));

        try {
            DB::connection()->getPdo();
            $this->info('PDO: OK');
            $one = DB::selectOne('SELECT 1 AS v');
            $this->line('SELECT 1: '.(string) ($one->v ?? '?'));
        } catch (Throwable $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $table = (string) config('legacy.usuario_table', 'usuario');
        if (! Schema::hasTable($table)) {
            $this->warn("Table [{$table}] does not exist on this database. Run: php artisan migrate");

            return self::FAILURE;
        }

        $count = DB::table($table)->count();
        $this->info("Table [{$table}]: exists ({$count} rows)");

        return self::SUCCESS;
    }
}
