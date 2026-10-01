<?php

// ============================================================
//  app/Models/Motorista.php
// ============================================================

namespace App\Models;

use App\Models\Concerns\InvalidaCacheDashboard;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Motorista extends Model
{
    use HasFactory, HasUuids, InvalidaCacheDashboard;

    protected $table = 'motoristas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nome', 'cpf', 'telefone', 'email', 'cnh_numero', 'cnh_categoria',
        'cnh_validade', 'turno_padrao', 'status', 'observacoes', 'permite_checkin_duplo',
    ];

    protected $casts = [
        'permite_checkin_duplo' => 'boolean',
    ];

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'motorista_id');
    }

    public function checkinAtivo(): HasOne
    {
        return $this->hasOne(Checkin::class, 'motorista_id')->where('status', 'ativo');
    }

    public function checkinsAtivos(): HasMany
    {
        return $this->hasMany(Checkin::class, 'motorista_id')->where('status', 'ativo')->orderBy('checkin_at');
    }

    public function limiteCheckinsAtivos(): int
    {
        return $this->permite_checkin_duplo ? 2 : 1;
    }

    /**
     * Check-in ativo a usar numa operação (viagem, abastecimento, checklist).
     * Com um único check-in ativo ele é usado direto; com dois (motoristas com
     * check-in duplo) o veículo precisa ser informado e pertencer a um deles.
     */
    public function resolverCheckinAtivo(?string $veiculoId): ?Checkin
    {
        $ativos = $this->checkinsAtivos()->get();

        if ($ativos->count() <= 1) {
            return $ativos->first();
        }

        if (! $veiculoId) {
            throw ValidationException::withMessages(['veiculo_id' => 'Selecione o veículo para esta operação.']);
        }

        return $ativos->firstWhere('veiculo_id', $veiculoId)
            ?? throw ValidationException::withMessages(['veiculo_id' => 'O veículo selecionado não pertence a um check-in ativo seu.']);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'motorista_id');
    }

    public function escalas(): HasMany
    {
        return $this->hasMany(Escala::class, 'motorista_id');
    }

    public function viagens(): HasMany
    {
        return $this->hasMany(Viagem::class, 'motorista_id');
    }

    public function abastecimentos(): HasMany
    {
        return $this->hasMany(Abastecimento::class, 'motorista_id');
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'motorista_unidade');
    }

    public function getDiasParaVencerCnhAttribute()
    {
        return now()->diffInDays($this->cnh_validade, false);
    }

    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = $value !== null ? preg_replace('/\D/', '', $value) : $value;
    }
}
