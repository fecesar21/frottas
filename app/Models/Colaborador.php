<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Colaborador sincronizado do AD (OU da unidade). Nunca é editado pela
 * aplicação: a fonte da verdade é o AD, via colaboradores:sincronizar.
 */
class Colaborador extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'colaboradores';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'unidade_id', 'ldap_guid', 'nome', 'samaccountname', 'email',
        'departamento', 'cargo', 'ativo', 'sincronizado_at',
    ];

    protected $casts = ['ativo' => 'boolean', 'sincronizado_at' => 'datetime'];

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * @return BelongsToMany<Viagem, $this>
     */
    public function viagens(): BelongsToMany
    {
        return $this->belongsToMany(Viagem::class, 'viagem_colaborador');
    }
}
