<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitacao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'solicitacoes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'usuario_id', 'unidade_id', 'motivo',
        'origem_tipo', 'origem_id', 'destino_tipo', 'destino_id', 'numero_atendimento',
        'cidade', 'hospital_destino', 'fornecedor_nome',
        'status', 'viagem_id', 'motorista_pendente_id', 'veiculo_pendente_id', 'motivo_recusa', 'observacoes',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * NÃO é uma relação Eloquent — é um método comum que resolve, em tempo
     * de execução, uma Unidade ou Localidade a partir de origem_tipo/origem_id.
     * Sempre chame como método: $solicitacao->origem(). NÃO use acesso de
     * propriedade sem parênteses ($solicitacao->origem) nem
     * Solicitacao::with(['origem']) / ->load(['origem']) — isso lança
     * LogicException, pois o Eloquent tentará tratá-lo como relação.
     */
    public function origem(): Unidade|Localidade|null
    {
        return $this->resolverPonto($this->origem_tipo, $this->origem_id);
    }

    /**
     * NÃO é uma relação Eloquent — mesmo aviso de origem(): sempre chame
     * como método $solicitacao->destino(), nunca via propriedade, with()
     * ou load().
     */
    public function destino(): Unidade|Localidade|null
    {
        return $this->resolverPonto($this->destino_tipo, $this->destino_id);
    }

    private function resolverPonto(?string $tipo, ?string $id): Unidade|Localidade|null
    {
        if (! $tipo || ! $id) {
            return null;
        }

        return match ($tipo) {
            'unidade' => Unidade::find($id),
            'localidade' => Localidade::find($id),
            default => null,
        };
    }

    /**
     * @return BelongsTo<Viagem, $this>
     */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    /**
     * @return BelongsTo<Motorista, $this>
     */
    public function motoristaPendente(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_pendente_id');
    }

    /**
     * @return BelongsTo<Veiculo, $this>
     */
    public function veiculoPendente(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_pendente_id');
    }
}
