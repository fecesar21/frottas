<?php

// ============================================================
//  app/Models/Usuario.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nome', 'cpf', 'email', 'senha_hash', 'perfil', 'ativo', 'ultimo_acesso', 'motorista_id', 'unidade_id',
        'ldap_guid', 'ldap_sync_at',
    ];

    protected $hidden = ['senha_hash'];

    // Sanctum usa a coluna senha_hash como password
    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = $value !== null ? preg_replace('/\D/', '', $value) : $value;
    }
}
