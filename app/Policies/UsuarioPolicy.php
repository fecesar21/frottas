<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $user->perfil === 'admin';
    }

    public function create(Usuario $user): bool
    {
        return $user->perfil === 'admin';
    }

    public function update(Usuario $user, Usuario $model): bool
    {
        return $user->perfil === 'admin';
    }

    public function delete(Usuario $user, Usuario $model): bool
    {
        return $user->perfil === 'admin' && $user->id !== $model->id;
    }
}
