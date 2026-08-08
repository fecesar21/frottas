<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistVeiculo extends Model
{
    use HasUuids;

    protected $table = 'checklists_veiculo';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'checkin_id', 'data_referencia', 'status',
        'itens_conforme', 'itens_nao_conforme', 'observacoes_gerais', 'enviado_at',
    ];

    protected $casts = [
        'data_referencia' => 'date',
        'enviado_at' => 'datetime',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(Checkin::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(ChecklistVeiculoResposta::class, 'checklist_veiculo_id');
    }
}
