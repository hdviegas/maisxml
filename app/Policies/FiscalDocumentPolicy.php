<?php

namespace App\Policies;

use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Services\Documents\DocumentListQuery;

class FiscalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return Organization::query()->forUser($user)->exists();
    }

    public function view(User $user, FiscalDocument $fiscalDocument): bool
    {
        return DocumentListQuery::forUser($user)
            ->whereKey($fiscalDocument->getKey())
            ->exists();
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
