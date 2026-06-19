<?php

namespace App\Policies;

use App\Models\Checkin;
use App\Models\Usuario;

class CheckinPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Checkin $checkin): bool
    {
        if (in_array($user->perfil, ['admin', 'gestor'])) {
            return true;
        }

        // Operador só vê o próprio checkin (via motorista_id vinculado)
        return $user->motorista_id === $checkin->motorista_id;
    }

    public function create(Usuario $user): bool
    {
        return true;
    }

    public function checkout(Usuario $user, Checkin $checkin): bool
    {
        if (in_array($user->perfil, ['admin', 'gestor'])) {
            return true;
        }

        return $user->motorista_id === $checkin->motorista_id;
    }
}
