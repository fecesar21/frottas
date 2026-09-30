<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistVeiculoItemModelo extends Model
{
    protected $table = 'checklist_veiculo_itens_modelo';

    protected $fillable = ['categoria_id', 'label', 'descricao', 'ordem', 'obrigatorio', 'requer_valor', 'valor_min', 'valor_max', 'somente_ambulancia', 'ativo'];

    protected $casts = [
        'obrigatorio' => 'boolean',
        'requer_valor' => 'boolean',
        'somente_ambulancia' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ChecklistVeiculoCategoria::class, 'categoria_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(ChecklistVeiculoResposta::class, 'item_modelo_id');
    }
}
