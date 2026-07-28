<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table          = 'users';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name',
        'email',
        'password_hash',
        'active',
    ];

    protected $validationRules = [
        'name'          => 'required|min_length[3]',
        'email'         => 'required|valid_email|is_unique[users.email]',
        'password_hash' => 'required',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'O nome é obrigatório.',
            'min_length' => 'O nome deve ter no mínimo 3 caracteres.',
        ],
        'email' => [
            'required'    => 'O e-mail é obrigatório.',
            'valid_email' => 'Informe um e-mail válido.',
            'is_unique'   => 'Este e-mail já está cadastrado.',
        ],
        'password_hash' => [
            'required' => 'A senha é obrigatória.',
        ],
    ];

    protected $skipValidation = false;
}
