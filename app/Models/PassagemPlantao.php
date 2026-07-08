<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassagemPlantao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'passagens_plantao';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_saindo_id', 'motorista_entrando_id',
        'checkin_saida_id', 'checkin_entrada_id',
        'turno_saindo', 'turno_entrando', 'km_momento', 'nivel_combustivel',
        'total_itens', 'itens_ok', 'itens_pendencia', 'observacoes_gerais', 'finalizado_at',
    ];

    protected $casts = ['finalizado_at' => 'datetime'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motoristaSaindo(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_saindo_id');
    }

    public function motoristaEntrando(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_entrando_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(ChecklistResposta::class, 'passagem_id');
    }
}
