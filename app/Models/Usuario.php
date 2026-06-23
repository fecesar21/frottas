<?php
// ============================================================
//  app/Models/Usuario.php
// ============================================================
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $table      = 'usuarios';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'nome', 'cpf', 'email', 'senha_hash', 'perfil', 'ativo', 'ultimo_acesso', 'motorista_id',
    ];

    protected $hidden = ['senha_hash'];

    // Sanctum usa a coluna senha_hash como password
    public function getAuthPassword() { return $this->senha_hash; }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_id');
    }
}
