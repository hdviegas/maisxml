<?php

namespace App\Console\Commands;

use App\Models\SiegAccount;
use App\Models\SiegSyncRun;
use App\Services\Sieg\SiegSyncRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SiegSyncCommand extends Command
{
    protected $signature = 'sieg:sync
        {--account= : ID da conta SIEG}
        {--max-runtime= : segundos (padrão config sieg.sync.max_runtime_seconds)}
        {--force : ignora pausa}';

    protected $description = 'Sincroniza os XMLs das contas SIEG (NF-e/NFC-e e eventos)';

    public function handle(SiegSyncRunner $runner): int
    {
        $maxRuntime = $this->option('max-runtime') !== null
            ? (int) $this->option('max-runtime')
            : (int) config('sieg.sync.max_runtime_seconds');
        if ($maxRuntime < 1) {
            $this->error('--max-runtime deve ser um número positivo de segundos.');

            return self::FAILURE;
        }
        $deadline = now()->addSeconds($maxRuntime);
        $force = (bool) $this->option('force');

        $accountId = $this->option('account') !== null ? (int) $this->option('account') : null;
        $accounts = $runner->accounts($accountId);

        if ($accounts->isEmpty()) {
            if ($accountId !== null) {
                $this->error('Conta SIEG não encontrada.');

                return self::FAILURE;
            }
            $this->warn('Nenhuma conta SIEG ativa para sincronizar.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = false;
        foreach ($runner->runAccounts($accounts, $deadline, $force) as $result) {
            $failed = $failed || $result['run']->status === 'failed';
            $rows[] = $this->row($result['account'], $result['run']);
        }

        $this->table(
            ['conta', 'status', 'requisições', 'baixados', 'importados', 'duplicados', 'ignorados', 'eventos', 'erros', 'mensagem'],
            $rows
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<mixed> */
    private function row(SiegAccount $account, SiegSyncRun $run): array
    {
        return [
            $account->id.' '.Str::limit((string) $account->name, 25),
            $run->status,
            $run->requests,
            $run->downloaded,
            $run->imported,
            $run->duplicates,
            $run->ignored,
            $run->events,
            $run->errors,
            Str::limit((string) $run->message, 70),
        ];
    }
}
