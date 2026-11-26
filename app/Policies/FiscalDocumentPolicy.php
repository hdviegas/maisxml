<?php

namespace App\Policies;

use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Documents\DocumentListQuery;
use App\Support\Tenant\Cnpj;

class FiscalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return Organization::query()->forUser($user)->exists();
    }

    public function view(User $user, FiscalDocument $fiscalDocument): bool
    {
        // Same rule as DocumentListQuery::forUser, evaluated on the loaded row (no per-row query).
        $digits = DocumentListQuery::cnpjDigitsForUser($user);
        $emit = Cnpj::digits((string) $fiscalDocument->getRawOriginal('cnpj_emit'));
        $dest = Cnpj::digits((string) $fiscalDocument->getRawOriginal('cnpj_dest'));

        return ($emit !== '' && in_array($emit, $digits, true))
            || ($dest !== '' && in_array($dest, $digits, true));
    }

    public function create(User $user): bool
    {
        return Organization::query()->forUser($user)->exists();
    }

    public function update(User $user, FiscalDocument $fiscalDocument): bool
    {
        return $this->view($user, $fiscalDocument);
    }

    public function delete(User $user, FiscalDocument $fiscalDocument): bool
    {
        return $this->view($user, $fiscalDocument);
    }
}
