<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unidades';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['nome', 'tipo', 'ativo'];

    public function motoristas(): BelongsToMany
    {
        return $this->belongsToMany(Motorista::class, 'motorista_unidade');
    }

    public function veiculos(): BelongsToMany
    {
        return $this->belongsToMany(Veiculo::class, 'veiculo_unidade');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
