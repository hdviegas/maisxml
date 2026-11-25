<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $cnpjEmpresa = '16657751000130';

        $organization = Organization::query()->create([
            'social' => 'Empresa demonstração',
            'fantasia' => 'Demo Mais XML',
            'cnpj' => $cnpjEmpresa,
        ]);

        $user = User::factory()->create([
            'social' => 'Usuário teste',
            'email' => 'teste@example.com',
            'login' => 'teste@example.com',
            'senha' => md5('password'),
            'cnpj' => $cnpjEmpresa,
        ]);

        Branch::query()->create([
            'empresa' => $organization->id,
            'social' => 'Matriz',
            'fantasia' => null,
            'cnpj' => $cnpjEmpresa,
            'entrada' => true,
        ]);
    }
}
