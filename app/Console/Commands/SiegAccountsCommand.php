<?php

namespace App\Console\Commands;

use App\Models\SiegAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SiegAccountsCommand extends Command
{
    protected $signature = 'sieg:accounts';

    protected $description = 'Lista as contas SIEG e o estado da última sincronização';

    public function handle(): int
    {
        $accounts = SiegAccount::query()->with('organization')->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('Nenhuma conta SIEG cadastrada. Use php artisan sieg:account.');

            return self::SUCCESS;
        }

        $rows = $accounts->map(function (SiegAccount $a) {
            $run = $a->runs()->latest('id')->first();

            return [
                $a->id,
                $a->organization_id.' '.Str::limit((string) ($a->organization?->social ?? '?'), 30),
                $a->name,
                $a->mode,
                $a->enabled ? 'sim' : 'não',
                $a->paused_until?->format('Y-m-d H:i') ?? '-',
                $a->last_nsu ?? '-',
                $a->last_success_at?->format('Y-m-d H:i') ?? '-',
                $a->consecutive_failures,
                Str::limit((string) $a->last_error, 60),
                $a->maskedKey(),
                $run
                    ? sprintf('%s imp=%d dup=%d ign=%d ev=%d err=%d', $run->status, $run->imported, $run->duplicates, $run->ignored, $run->events, $run->errors)
                    : '-',
            ];
        })->all();

        $this->table(
            ['id', 'empresa', 'nome', 'modo', 'ativa', 'pausada até', 'last_nsu', 'último sucesso', 'falhas', 'último erro', 'chave', 'última execução'],
            $rows
        );

        return self::SUCCESS;
    }
}
