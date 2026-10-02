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
     * o check-in: KM de saída + o que rodou por conta de manutenção.
     * Retorna null quando houve viagem (vale o kmRetornoEsperado()).
     */
    public function kmRetornoSemViagem(): ?int
    {
        if ($this->viagensDoPeriodo()->exists()) {
            return null;
        }

        return (int) $this->km_saida + $this->kmManutencoesDoPeriodo();
    }

    /**
     * KM de retorno esperado: KM de saída + KM percorridos nas viagens
     * concluídas e nas manutenções do veículo desde o check-in. Diferença
     * indica quilometragem rodada sem registro (ou KM lançado errado).
     */
    public function kmRetornoEsperado(): int
    {
        return (int) $this->km_saida
            + (int) $this->viagensDoPeriodo()->whereNotNull('km_chegada')->sum(DB::raw('km_chegada - km_saida'))
            + $this->kmManutencoesDoPeriodo();
    }

    /**
     * Cada manutenção é um trecho: do último KM conhecido antes dela (saída do
     * check-in ou chegada das viagens anteriores) até o KM de saída da
     * oficina. O km_atual do veículo não serve de base porque viagens não o
     * atualizam.
     */
    private function kmManutencoesDoPeriodo(): int
    {
        if (! $this->checkin_at) {
            return 0;
        }

        return (int) VeiculoManutencao::where('veiculo_id', $this->veiculo_id)
            ->where('inicio', '>=', $this->checkin_at)
            ->get()
            ->sum(function (VeiculoManutencao $m) {
                $kmFinal = $m->km_saida ?? $m->km_entrada;
                if ($kmFinal === null) {
                    return 0;
                }

                $kmAntes = max((int) $this->km_saida, (int) $this->viagensDoPeriodo()
                    ->where('saida_at', '<=', $m->inicio)
                    ->max('km_chegada'));

                return max(0, (int) $kmFinal - $kmAntes);
            });
    }

    /** Viagem do veículo deste check-in ainda em andamento, se houver. */
    public function viagemEmAndamento(): ?Viagem
    {
        return Viagem::where('veiculo_id', $this->veiculo_id)->where('status', 'em_andamento')->first();
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
