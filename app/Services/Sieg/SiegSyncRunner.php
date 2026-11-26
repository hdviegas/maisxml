<?php

namespace App\Services\Sieg;

use App\Models\SiegAccount;
use App\Models\SiegSyncRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Escolhe as contas SIEG e sincroniza cada uma até o prazo (usado pelo comando e pelo endpoint de cron).
 */
class SiegSyncRunner
{
    public function __construct(private readonly SiegSyncService $service) {}

    /**
     * @return Collection<int, SiegAccount>
     */
    public function accounts(?int $accountId = null): Collection
    {
        if ($accountId !== null) {
            $account = SiegAccount::query()->find($accountId);

            return $account === null ? collect() : collect([$account]);
        }

        return SiegAccount::query()->runnable()->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, SiegAccount>  $accounts
     * @return list<array{account: SiegAccount, run: SiegSyncRun}>
     */
    public function runAccounts(Collection $accounts, CarbonInterface $deadline, bool $force = false): array
    {
        $results = [];
        // Contas "compras" primeiro: fazem 1-2 requisições (baixar-xmls, 2/min) e deixam o resto
        // do prazo para o NSU, que senão consumiria a rodada inteira.
        $ordered = $accounts->sortBy([
            fn (SiegAccount $a, SiegAccount $b) => ($b->mode === SiegAccount::MODE_COMPRAS) <=> ($a->mode === SiegAccount::MODE_COMPRAS),
            ['id', 'asc'],
        ]);

        foreach ($ordered as $account) {
            $results[] = ['account' => $account, 'run' => $this->service->sync($account, $deadline, $force)];
        }

        return $results;
    }

    /**
     * @return list<array{account: SiegAccount, run: SiegSyncRun}>
     */
    public function runAll(CarbonInterface $deadline, bool $force = false, ?int $accountId = null): array
    {
        return $this->runAccounts($this->accounts($accountId), $deadline, $force);
    }
}
