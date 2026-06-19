<?php

namespace App\Policies;

use App\Models\Motorista;
use App\Models\Usuario;

class MotoristaPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Motorista $motorista): bool
    {
        return true;
    }

    public function create(Usuario $user): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }

    public function update(Usuario $user, Motorista $motorista): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }

    public function delete(Usuario $user, Motorista $motorista): bool
    {
        return in_array($user->perfil, ['admin', 'gestor']);
    }
}
