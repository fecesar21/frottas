<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Viagem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'viagens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'checkin_id',
        'origem', 'destino', 'motivo_viagem', 'numero_atendimento',
        'km_saida', 'km_chegada',
        'saida_at', 'chegada_at', 'status', 'observacoes',
    ];

    /** Rótulos de exibição dos motivos de viagem (relatórios). */
    public const ROTULOS_MOTIVO = [
        'transferencia_paciente' => 'Transferência de Paciente',
        'buscar_medico' => 'Buscar Médico em Outra Cidade',
        'material_outro_hospital' => 'Levar Material em Outro Hospital',
        'transporte_colaborador' => 'Transporte de Colaborador(es)',
        'buscar_material_fornecedor' => 'Buscar Materiais em Fornecedor',
        'tfd' => 'TFD',
        'alimentacao' => 'Alimentação (Levar/Buscar)',
        'servicos_administrativos' => 'Serviços Administrativos Diversos',
    ];

    public static function rotuloMotivo(?string $motivo): string
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return 'Não informado';
        }

        return self::ROTULOS_MOTIVO[$motivo] ?? Str::title(str_replace('_', ' ', $motivo));
    }

    protected $casts = ['saida_at' => 'datetime', 'chegada_at' => 'datetime'];

    /**
     * Origem e destino são sempre gravados em maiúsculas (padronização),
     * independentemente de onde a viagem foi criada.
     */
    protected function origem(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v === null ? null : mb_strtoupper(trim($v)));
    }

    protected function destino(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v === null ? null : mb_strtoupper(trim($v)));
    }

    /**
     * @return BelongsTo<Veiculo, $this>
     */
    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /**
     * @return BelongsTo<Motorista, $this>
     */
    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    /**
     * @return HasMany<ViagemPonto, $this>
     */
    public function pontos(): HasMany
    {
        return $this->hasMany(ViagemPonto::class)->orderBy('capturado_at');
    }

    /**
     * Colaboradores transportados (motivo transporte_colaborador).
     *
     * @return BelongsToMany<Colaborador, $this>
     */
    public function colaboradores(): BelongsToMany
    {
        return $this->belongsToMany(Colaborador::class, 'viagem_colaborador')->orderBy('nome');
    }

    /**
     * @return HasOne<Solicitacao, $this>
     */
    public function solicitacao(): HasOne
    {
        return $this->hasOne(Solicitacao::class);
    }
}
