<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\SiegAccount;
use App\Support\Tenant\Cnpj;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SiegAccountCommand extends Command
{
    protected $signature = 'sieg:account
        {organization : ID da empresa ou CNPJ}
        {--name= : Nome da conta (padrão: razão social/fantasia da empresa)}
        {--mode=nsu : nsu|periodo|compras}
        {--enable : Habilita a conta}
        {--disable : Desabilita a conta}
        {--rotate-key : Troca a API Key}
        {--copy-key-from= : ID de outra conta da MESMA empresa cuja API Key será reutilizada (sem pergunta)}';

    protected $description = 'Cadastra ou atualiza a conta SIEG de uma empresa (a API Key é pedida de forma oculta)';

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        if (! in_array($mode, SiegAccount::MODES, true)) {
            $this->error('Modo inválido. Use nsu, periodo ou compras.');

            return self::FAILURE;
        }
        if ($this->option('enable') && $this->option('disable')) {
            $this->error('Use --enable ou --disable, não ambos.');

            return self::FAILURE;
        }

        $org = $this->resolveOrganization((string) $this->argument('organization'));
        if ($org === null) {
            return self::FAILURE;
        }

        $copyKey = null;
        if ($this->option('copy-key-from') !== null && $this->option('copy-key-from') !== '') {
            $source = SiegAccount::query()->find((int) $this->option('copy-key-from'));
            if ($source === null || (int) $source->organization_id !== (int) $org->id) {
                $this->error('--copy-key-from: conta inexistente ou de outra empresa.');

                return self::FAILURE;
            }
            $copyKey = (string) $source->api_key;
        }

        $name = trim((string) ($this->option('name') ?: ($org->fantasia ?: $org->social)));
        if ($name === '') {
            $name = 'Empresa '.$org->id;
        }
        $name = mb_substr($name, 0, 120);

        $account = SiegAccount::query()
            ->where('organization_id', $org->id)
            ->where('name', $name)
            ->first();
        $created = $account === null;

        if ($created) {
            $key = $copyKey ?? $this->askKey();
            if ($key === null) {
                return self::FAILURE;
            }
            $account = new SiegAccount([
                'organization_id' => $org->id,
                'name' => $name,
                'api_key' => $key,
                'mode' => $mode,
                'enabled' => ! $this->option('disable'),
            ]);
        } else {
            if ($this->option('rotate-key')) {
                $key = $copyKey ?? $this->askKey();
                if ($key === null) {
                    return self::FAILURE;
                }
                $account->api_key = $key;
                $account->consecutive_failures = 0;
                $account->last_error = null;
                $account->paused_until = null;
            }
            if ($this->input->hasParameterOption('--mode')) {
                $account->mode = $mode;
            }
            if ($this->option('enable')) {
                $account->enabled = true;
            }
            if ($this->option('disable')) {
                $account->enabled = false;
            }
        }

        $account->save();

        $this->info($created ? 'Conta SIEG criada.' : 'Conta SIEG atualizada.');
        $this->table(['Campo', 'Valor'], [
            ['id', $account->id],
            ['empresa', $org->id.' - '.$org->social],
            ['nome', $account->name],
            ['modo', $account->mode],
            ['habilitada', $account->enabled ? 'sim' : 'não'],
            ['API Key', $account->maskedKey()],
        ]);

        return self::SUCCESS;
    }

    private function askKey(): ?string
    {
        $key = trim((string) $this->secret('API Key SIEG (não será exibida)'));
        if ($key === '' || strlen($key) < 16) {
            $this->error('API Key inválida (vazia ou com menos de 16 caracteres).');

            return null;
        }

        return $key;
    }

    private function resolveOrganization(string $input): ?Organization
    {
        $digits = Cnpj::digits($input);
        $matches = new Collection;

        // Até 9 dígitos: id; CNPJ tem 14 (ou 11 de CPF).
        if ($digits !== '' && ctype_digit($input) && strlen($digits) < 11) {
            $org = Organization::query()->find((int) $digits);
            if ($org !== null) {
                return $org;
            }
            $this->error("Empresa {$digits} não encontrada.");

            return null;
        }

        if ($digits === '') {
            $this->error('Informe o ID da empresa ou o CNPJ.');

            return null;
        }

        $matches = Organization::query()
            ->whereKey(Organization::idsMatchingCnpj($digits))
            ->get();

        $branchOrgIds = Branch::query()
            ->whereKey(Branch::codesMatchingCnpj($digits))
            ->pluck('empresa')
            ->unique();
        if ($branchOrgIds->isNotEmpty()) {
            $matches = $matches->merge(Organization::query()->whereIn('id', $branchOrgIds)->get());
        }

        $matches = $matches->unique('id')->values();

        if ($matches->isEmpty()) {
            $this->error("Nenhuma empresa encontrada para o CNPJ {$digits}.");

            return null;
        }
        if ($matches->count() > 1) {
            $this->error('CNPJ ambíguo; use o ID da empresa: '.$matches->pluck('id')->implode(', '));

            return null;
        }

        return $matches->first();
    }
}
