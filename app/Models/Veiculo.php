<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Veiculo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'veiculos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'placa', 'modelo', 'marca', 'ano', 'cor', 'chassi', 'renavam',
        'combustivel', 'capacidade_tanque', 'km_atual', 'km_proxima_revisao',
        'status', 'manutencao_inicio', 'observacoes',
    ];

    protected $casts = [
        'manutencao_inicio' => 'datetime',
    ];

    // Relações
    public function checkinAtivo(): HasOne
    {
        return $this->hasOne(Checkin::class, 'veiculo_id')->where('status', 'ativo');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'veiculo_id');
    }

    public function kmRegistros(): HasMany
    {
        return $this->hasMany(KmRegistro::class, 'veiculo_id');
    }

    public function abastecimentos(): HasMany
    {
        return $this->hasMany(Abastecimento::class, 'veiculo_id');
    }

    /**
     * @return HasMany<Viagem, $this>
     */
    public function viagens(): HasMany
    {
        return $this->hasMany(Viagem::class, 'veiculo_id');
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'veiculo_unidade');
    }

    public function checklistsVeiculo(): HasMany
    {
        return $this->hasMany(ChecklistVeiculo::class, 'veiculo_id');
    }

    public function checklistHoje(): HasOne
    {
        return $this->hasOne(ChecklistVeiculo::class, 'veiculo_id')->whereDate('data_referencia', now()->toDateString());
    }

    public function getPrecisaManutencaoAttribute(): bool
    {
        return $this->km_proxima_revisao !== null && $this->km_atual >= $this->km_proxima_revisao;
    }
}
