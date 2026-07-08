<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Viagem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'viagens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'checkin_id',
        'origem', 'destino', 'km_saida', 'km_chegada',
        'saida_at', 'chegada_at', 'status', 'observacoes',
    ];

    protected $casts = ['saida_at' => 'datetime', 'chegada_at' => 'datetime'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(ViagemPonto::class)->orderBy('capturado_at');
    }
}
