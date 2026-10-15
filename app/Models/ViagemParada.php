<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parada intermediária no trajeto de uma viagem (ex.: buscar acompanhante).
 * Aberta enquanto `saida_at` for nulo.
 */
class ViagemParada extends Model
{
    use HasUuids;

    protected $table = 'viagem_paradas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['viagem_id', 'ordem', 'local', 'motivo', 'chegada_at', 'saida_at'];

    protected $casts = ['chegada_at' => 'datetime', 'saida_at' => 'datetime', 'ordem' => 'integer'];

    protected function local(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v === null ? null : mb_strtoupper(trim($v)));
    }

    /**
     * @return BelongsTo<Viagem, $this>
     */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }
}
