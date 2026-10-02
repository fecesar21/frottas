<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCacheDashboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Checkin extends Model
{
    use HasFactory, HasUuids, InvalidaCacheDashboard;

    protected $table = 'checkins';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'motorista_id', 'veiculo_id', 'escala_id', 'turno',
        'km_saida', 'km_retorno', 'km_retorno_esperado', 'divergencia_km', 'justificativa_divergencia_km', 'nivel_combustivel_saida', 'nivel_combustivel_retorno',
        'checkin_at', 'checkout_at', 'status', 'ocorrencias',
    ];

    protected $casts = ['checkin_at' => 'datetime', 'checkout_at' => 'datetime'];

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class);
    }

    public function checklistVeiculo(): HasOne
    {
        return $this->hasOne(ChecklistVeiculo::class, 'checkin_id');
    }

    /**
     * KM de retorno obrigatório quando o veículo não fez nenhuma viagem desde
     * o check-in: o odômetro não pode ter andado. Considera o KM atual do
     * veículo porque uma manutenção no período pode tê-lo atualizado.
     * Retorna null quando houve viagem (qualquer KM ≥ saída é aceito).
     */
    public function kmRetornoSemViagem(): ?int
    {
        if ($this->viagensDoPeriodo()->exists()) {
            return null;
        }

        return max((int) $this->km_saida, (int) Veiculo::whereKey($this->veiculo_id)->value('km_atual'));
    }

    /**
     * KM de retorno esperado: KM de saída + KM percorridos nas viagens
     * concluídas do veículo desde o check-in. Diferença indica quilometragem
     * rodada sem viagem registrada (ou KM de viagem lançado errado).
     */
    public function kmRetornoEsperado(): int
    {
        return $this->kmRetornoSemViagem()
            ?? (int) $this->km_saida + (int) $this->viagensDoPeriodo()
                ->whereNotNull('km_chegada')
                ->sum(DB::raw('km_chegada - km_saida'));
    }

    /** Viagens do veículo feitas durante este check-in. */
    private function viagensDoPeriodo(): Builder
    {
        return Viagem::where('veiculo_id', $this->veiculo_id)
            ->where(function ($q) {
                $q->where('checkin_id', $this->id);
                if ($this->checkin_at) {
                    $q->orWhere('saida_at', '>=', $this->checkin_at);
                }
            });
    }
}
