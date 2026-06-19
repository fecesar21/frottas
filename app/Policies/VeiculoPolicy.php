<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\Veiculo;

class VeiculoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Veiculo $veiculo): bool
    {
        return true;
    }

    public function create(Usuario $user): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }

    public function update(Usuario $user, Veiculo $veiculo): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }

    public function delete(Usuario $user, Veiculo $veiculo): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }
}
