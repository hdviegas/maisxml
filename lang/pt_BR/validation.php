<?php

/**
 * Regras usadas com frequência em pt-BR. Demais chaves usam lang/{fallback_locale}/validation.php.
 */
return [

    'accepted' => 'O campo :attribute deve ser aceito.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'filled' => 'O campo :attribute deve ter um valor.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'present' => 'O campo :attribute deve estar presente.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser texto.',
    'unique' => 'O :attribute informado já está em uso.',

    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O campo :attribute não pode ser maior que :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],

    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O campo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    'attributes' => [
        'login' => 'usuário',
        'password' => 'senha',
        'email' => 'e-mail',
        'name' => 'nome',
        'remember' => 'lembrar-me',
    ],
];
