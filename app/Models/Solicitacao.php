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
        'origem_unidade_id', 'destino_unidade_id', 'numero_atendimento',
        'cidade', 'hospital_destino', 'fornecedor_nome',
        'status', 'viagem_id', 'motorista_pendente_id', 'veiculo_pendente_id', 'observacoes',
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
     * @return BelongsTo<Unidade, $this>
     */
    public function origemUnidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'origem_unidade_id');
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function destinoUnidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'destino_unidade_id');
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
