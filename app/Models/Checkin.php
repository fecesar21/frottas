<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCacheDashboard;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checkin extends Model
{
    use HasFactory, HasUuids, InvalidaCacheDashboard;

    protected $table = 'checkins';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'motorista_id', 'veiculo_id', 'escala_id', 'turno',
        'km_saida', 'km_retorno', 'nivel_combustivel_saida', 'nivel_combustivel_retorno',
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
        $teveViagem = Viagem::where('veiculo_id', $this->veiculo_id)
            ->where(function ($q) {
                $q->where('checkin_id', $this->id);
                if ($this->checkin_at) {
                    $q->orWhere('saida_at', '>=', $this->checkin_at);
                }
            })
            ->exists();

        if ($teveViagem) {
            return null;
        }

        return max((int) $this->km_saida, (int) Veiculo::whereKey($this->veiculo_id)->value('km_atual'));
    }
}
