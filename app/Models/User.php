<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['social', 'email', 'login', 'senha', 'cnpj', 'tipo', 'email_verified_at'])]
#[Hidden(['senha', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public $timestamps = false;

    public function getTable(): string
    {
        return config('legacy.usuario_table', 'usuario');
    }

    /**
     * Legacy passwords are MD5 hashes in `senha`. Laravel's `password` attribute maps here.
     */
    protected function casts(): array
    {
        return [
            'tipo' => 'integer',
            'email_verified_at' => 'datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => $attributes['social'] ?? '',
        );
    }

    /**
     * Legacy `usuario` has no remember_token column. An empty name makes
     * Authenticatable::getRememberToken()/setRememberToken() no-ops, so the
     * guard never reads or writes the column (incl. SessionGuard::logout()).
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public function emailForPasswordReset(): string
    {
        $email = trim((string) $this->email);

        return $email !== '' ? $email : 'legacy-user-'.$this->getKey().'@imports.maisxml';
    }
}
