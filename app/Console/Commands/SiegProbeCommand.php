<?php

namespace App\Console\Commands;

use App\Models\SiegAccount;
use App\Services\Sieg\SiegClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SiegProbeCommand extends Command
{
    protected $signature = 'sieg:probe
        {account : ID da conta SIEG}
        {--yes : não perguntar}';

    protected $description = 'Diagnóstico: faz chamadas REAIS à API SIEG e mostra apenas a estrutura das respostas';

    public function handle(SiegClient $client): int
    {
        $account = SiegAccount::query()->find((int) $this->argument('account'));
        if ($account === null) {
            $this->error('Conta SIEG não encontrada.');

            return self::FAILURE;
        }

        $this->warn('Atenção: este comando faz ~3 chamadas REAIS à API da SIEG (create-jwt, consultar-nsu e baixar-xmls), consumindo o limite de requisições da conta.');
        if (! $this->option('yes') && ! $this->confirm('Deseja continuar?', false)) {
            $this->info('Cancelado. Nenhuma chamada foi feita.');

            return self::SUCCESS;
        }

        $result = $client->probe($account->connection());
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $path = storage_path('logs/sieg-probe-'.$account->id.'-'.now()->format('Ymd-His').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);

        $this->line($json);
        $this->info('Resultado salvo em '.$path);

        return self::SUCCESS;
    }
}
