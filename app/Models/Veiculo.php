<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCacheDashboard;
use App\Support\Plantao;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Veiculo extends Model
{
    use HasFactory, HasUuids, InvalidaCacheDashboard;

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

    public function ehAmbulancia(): bool
    {
        return str_contains(mb_strtoupper(Str::ascii((string) $this->modelo)), 'AMBULANCIA');
    }

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
        // Checklist do plantão vigente (07h–19h / 19h–07h), não do dia do calendário.
        $plantao = Plantao::atual();

        return $this->hasOne(ChecklistVeiculo::class, 'veiculo_id')
            ->whereDate('data_referencia', $plantao['data'])
            ->where('turno', $plantao['turno']);
    }

    public function getPrecisaManutencaoAttribute(): bool
    {
        return $this->km_proxima_revisao !== null && $this->km_atual >= $this->km_proxima_revisao;
    }

    public function manutencoes(): HasMany
    {
        return $this->hasMany(VeiculoManutencao::class);
    }

    public function manutencaoAberta(): HasOne
    {
        return $this->hasOne(VeiculoManutencao::class)->whereNull('fim')->latestOfMany('inicio');
    }

    public function emManutencao(): bool
    {
        return $this->status === 'manutencao';
    }

    /**
     * Veículo em manutenção não recebe solicitações nem registra viagens.
     */
    public function garantirForaDeManutencao(string $campo = 'veiculo_id'): void
    {
        if (! $this->emManutencao()) {
            return;
        }

        $aberta = $this->manutencaoAberta;
        $desde = ($aberta?->inicio ?? $this->manutencao_inicio)?->format('d/m H:i');
        $tipo = $aberta?->tipo ? ' ('.VeiculoManutencao::rotuloTipo($aberta->tipo).')' : '';

        throw ValidationException::withMessages([
            $campo => "Veículo {$this->placa} em manutenção".($desde ? " desde {$desde}" : '').$tipo.'.',
        ]);
    }
}
