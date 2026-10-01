<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VeiculoManutencao extends Model
{
    use HasFactory, HasUuids;

    public const TIPOS = [
        'troca_pneus' => 'Troca de pneus',
        'troca_oleo' => 'Troca de óleo do motor',
        'revisao' => 'Revisão',
        'mecanica' => 'Mecânica',
        'funilaria_pintura' => 'Funilaria / Pintura',
        'outro' => 'Outro',
    ];

    protected $table = 'veiculo_manutencoes';

    protected $fillable = [
        'veiculo_id', 'inicio', 'fim', 'tipo', 'motivo', 'origem',
        'aberta_por_id', 'fechada_por_id', 'km_entrada', 'km_saida', 'local', 'observacao_saida',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
        'km_entrada' => 'integer',
        'km_saida' => 'integer',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function abertaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aberta_por_id');
    }

    public function fechadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fechada_por_id');
    }

    public static function rotuloTipo(?string $tipo): string
    {
        return self::TIPOS[$tipo] ?? 'Não informado';
    }
}
