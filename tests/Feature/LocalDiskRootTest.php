<?php

use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('download finds xml_entrada file when local disk root is reconfigured', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $root = sys_get_temp_dir().'/maisxml_root_'.uniqid();
    config(['filesystems.disks.local.root' => $root]);
    Storage::forgetDisk('local');

    $chave = str_repeat('5', 44);
    $emitter = '16657751000130';
    $path = "xml_entrada/{$emitter}/".now()->format('m-y')."/{$chave}-NFe.xml";
    Storage::disk('local')->put($path, sample_nfe_xml($chave));
    expect(file_exists($root.'/'.$path))->toBeTrue();

    $doc = FiscalDocument::query()->create([
        'url' => $path, 'chave' => $chave, 'empresa' => $emitter, 'social' => '', 'dest' => '', 'tipo' => '',
        'data_nf' => now(), 'ip' => '127.0.0.1', 'cnpj_emit' => $emitter, 'cnpj_dest' => '12345678901',
        'social_emit' => null, 'social_dest' => null, 'numero_nf' => 98, 'modelo_nf' => '55', 'serie_nf' => '1',
        'total_nf' => '10.00', 'total_icms' => 0, 'total_icms_st' => null, 'total_frete' => 0,
        'cfop_nf' => '5102', 'cancelada' => false, 'sped' => null,
    ]);

    $this->actingAs($user)->get(route('documents.download', $doc))->assertOk();

    File::deleteDirectory($root);
    Storage::forgetDisk('local');
});

/**
 * Avalia config/filesystems.php com FILESYSTEM_LOCAL_ROOT temporário e devolve a raiz do disco local.
 */
function local_root_for(?string $value): string
{
    $previous = [$_ENV['FILESYSTEM_LOCAL_ROOT'] ?? null, $_SERVER['FILESYSTEM_LOCAL_ROOT'] ?? null];

    if ($value === null) {
        unset($_ENV['FILESYSTEM_LOCAL_ROOT'], $_SERVER['FILESYSTEM_LOCAL_ROOT']);
    } else {
        $_ENV['FILESYSTEM_LOCAL_ROOT'] = $_SERVER['FILESYSTEM_LOCAL_ROOT'] = $value;
    }
    putenv($value === null ? 'FILESYSTEM_LOCAL_ROOT' : 'FILESYSTEM_LOCAL_ROOT='.$value);

    try {
        return (require config_path('filesystems.php'))['disks']['local']['root'];
    } finally {
        foreach (['_ENV' => $previous[0], '_SERVER' => $previous[1]] as $bag => $old) {
            if ($old === null) {
                unset($GLOBALS[$bag]['FILESYSTEM_LOCAL_ROOT']);
            } else {
                $GLOBALS[$bag]['FILESYSTEM_LOCAL_ROOT'] = $old;
            }
        }
        putenv('FILESYSTEM_LOCAL_ROOT');
    }
}

test('relative FILESYSTEM_LOCAL_ROOT ".." resolves to the parent of the project', function () {
    expect(local_root_for('..'))->toBe(rtrim(realpath(dirname(base_path())), '/'));
});

test('relative root that does not exist is joined and normalized without trailing slash', function () {
    $expected = rtrim(str_replace('\\', '/', base_path()), '/').'/nao/existe';

    expect(local_root_for('./nao/existe/'))->toBe($expected);
    expect(local_root_for('nao/x/../existe'))->toBe($expected);
});

test('absolute FILESYSTEM_LOCAL_ROOT passes through', function () {
    $dir = sys_get_temp_dir().'/maisxml_abs_'.uniqid();
    mkdir($dir);

    expect(local_root_for($dir.'/'))->toBe(rtrim(realpath($dir), '/'));
    expect(local_root_for('/caminho/inexistente/xml'))->toBe('/caminho/inexistente/xml');

    rmdir($dir);
});

test('empty or missing FILESYSTEM_LOCAL_ROOT falls back to storage/app/private', function () {
    expect(local_root_for(''))->toBe(storage_path('app/private'));
    expect(local_root_for('   '))->toBe(storage_path('app/private'));
    expect(local_root_for(null))->toBe(storage_path('app/private'));
});

test('download works for xml/ document when root is a relative path to a temp folder', function () {
    $this->seed();
    $user = User::query()->where('email', 'teste@example.com')->firstOrFail();

    $tmp = sys_get_temp_dir().'/maisxml_rel_'.uniqid();
    mkdir($tmp);
    $up = str_repeat('../', count(array_filter(explode('/', base_path()))));
    $relative = $up.ltrim(realpath($tmp), '/');

    $root = local_root_for($relative);
    expect($root)->toBe(rtrim(realpath($tmp), '/'));
    config(['filesystems.disks.local.root' => $root]);
    Storage::forgetDisk('local');

    $chave = str_repeat('6', 44);
    $emitter = '16657751000130';
    $path = "xml/{$emitter}/".now()->format('m-y')."/{$chave}-NFe.xml";
    Storage::disk('local')->put($path, sample_nfe_xml($chave));
    expect(file_exists($tmp.'/'.$path))->toBeTrue();

    $doc = FiscalDocument::query()->create([
        'url' => $path, 'chave' => $chave, 'empresa' => $emitter, 'social' => '', 'dest' => '', 'tipo' => '',
        'data_nf' => now(), 'ip' => '127.0.0.1', 'cnpj_emit' => $emitter, 'cnpj_dest' => '12345678901',
        'social_emit' => null, 'social_dest' => null, 'numero_nf' => 99, 'modelo_nf' => '55', 'serie_nf' => '1',
        'total_nf' => '10.00', 'total_icms' => 0, 'total_icms_st' => null, 'total_frete' => 0,
        'cfop_nf' => '5102', 'cancelada' => false, 'sped' => null,
    ]);

    $this->actingAs($user)->get(route('documents.download', $doc))->assertOk();

    File::deleteDirectory($tmp);
    Storage::forgetDisk('local');
});
