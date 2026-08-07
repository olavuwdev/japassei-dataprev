<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Auth\Permissions;
use CodeIgniter\Model;

class UserPermissionModel extends Model
{
    protected $table         = 'user_permissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'user_id',
        'permission',
    ];

    /**
     * Códigos concedidos ao usuário, já filtrados pelo catálogo — uma permissão
     * removida do código não deve continuar valendo só por estar no banco.
     *
     * @return list<string>
     */
    public function forUser(int $userId): array
    {
        $rows = $this->where('user_id', $userId)->findAll();

        return array_values(array_filter(
            array_map(static fn (array $row): string => (string) $row['permission'], $rows),
            static fn (string $code): bool => Permissions::isValid($code)
        ));
    }

    /**
     * Permissões de vários usuários de uma vez, indexadas por user_id.
     *
     * @param  list<int> $userIds
     * @return array<int, list<string>>
     */
    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows   = $this->whereIn('user_id', $userIds)->findAll();
        $result = [];

        foreach ($rows as $row) {
            $code = (string) $row['permission'];

            if (Permissions::isValid($code)) {
                $result[(int) $row['user_id']][] = $code;
            }
        }

        return $result;
    }

    /**
     * Substitui todas as permissões do usuário pelo conjunto informado.
     *
     * @param list<string> $codes
     */
    public function sync(int $userId, array $codes): void
    {
        $codes = Permissions::normalize($codes);

        $this->where('user_id', $userId)->delete();

        if ($codes === []) {
            return;
        }

        $this->insertBatch(array_map(static fn (string $code): array => [
            'user_id'    => $userId,
            'permission' => $code,
        ], $codes));
    }
}
