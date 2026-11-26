<?php

use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Policies\FiscalDocumentPolicy;
use App\Services\Documents\DocumentListQuery;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;

const NPQ_SUPPLIER = '99888777000166';

function npq_tenant(string $cnpj, string $email): User
{
    $org = Organization::query()->create(['social' => $email, 'fantasia' => $email, 'cnpj' => $cnpj]);
    $user = User::factory()->create(['cnpj' => $cnpj, 'email' => $email]);
    Branch::query()->create(['empresa' => $org->id, 'social' => $email, 'fantasia' => null, 'cnpj' => $cnpj, 'entrada' => true]);

    return $user;
}

function npq_doc(string $fill, string $emit, string $dest): FiscalDocument
{
    return FiscalDocument::query()->create(legacy_xml_row([
        'url' => 'x',
        'chave' => chave_for($fill, $emit),
        'data_nf' => now()->toDateString(),
        'cnpj_emit' => $emit,
        'cnpj_dest' => $dest,
    ]));
}

function npq_page_queries(User $user): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($user)->get('/documentos')->assertOk();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

test('npq: documents page query count does not grow with rows', function () {
    $user = npq_tenant('11222333000181', 'a@example.com');
    for ($i = 1; $i <= 3; $i++) {
        npq_doc((string) $i, NPQ_SUPPLIER, '11222333000181');
    }
    $few = npq_page_queries($user);

    for ($i = 4; $i <= 15; $i++) {
        npq_doc((string) $i, NPQ_SUPPLIER, '11222333000181');
    }
    $many = npq_page_queries($user);

    fwrite(STDERR, "NPQ queries few=$few many=$many\n");
    expect($many)->toBe($few)->and($many)->toBeLessThan(25);
});

test('npq: policy allows own emit/dest and denies other company without queries', function () {
    $a = npq_tenant('11222333000181', 'a@example.com');
    $b = npq_tenant('44555666000199', 'b@example.com');
    $asDest = npq_doc('1', NPQ_SUPPLIER, '11222333000181');
    $asEmit = npq_doc('2', '11222333000181', '55444333000122');
    $others = npq_doc('3', NPQ_SUPPLIER, '44555666000199');

    $policy = new FiscalDocumentPolicy;
    expect($policy->view($a, $asDest))->toBeTrue()
        ->and($policy->view($a, $asEmit))->toBeTrue()
        ->and($policy->view($a, $others))->toBeFalse()
        ->and($policy->delete($a, $others))->toBeFalse()
        ->and($policy->view($b, $others))->toBeTrue()
        ->and($policy->view($b, $asDest))->toBeFalse();
});

test('npq: request memoization does not leak between users', function () {
    $a = npq_tenant('11222333000181', 'a@example.com');
    $b = npq_tenant('44555666000199', 'b@example.com');

    $route = new Route('GET', '/x', fn () => null);
    $req = request();
    $req->setRouteResolver(fn () => $route);

    expect(DocumentListQuery::cnpjDigitsForUser($a))->toBe(['11222333000181'])
        ->and(DocumentListQuery::cnpjDigitsForUser($b))->toBe(['44555666000199'])
        ->and(DocumentListQuery::cnpjDigitsForUser($a))->toBe(['11222333000181']);

    // Memoized within the routed request: a new branch is not seen until the next request.
    Branch::query()->create(['empresa' => Organization::query()->forUser($a)->value('id'), 'social' => 'F', 'fantasia' => null, 'cnpj' => '77666555000144', 'entrada' => true]);
    expect(DocumentListQuery::cnpjDigitsForUser($a))->toBe(['11222333000181']);
});
