<?php
// ============================================================
//  app/Models/Usuario.php
// ============================================================
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table      = 'usuarios';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'nome', 'email', 'senha_hash', 'perfil', 'ativo', 'ultimo_acesso', 'motorista_id',
    ];

    protected $hidden = ['senha_hash'];

    // Sanctum usa a coluna senha_hash como password
    public function getAuthPassword() { return $this->senha_hash; }
}
