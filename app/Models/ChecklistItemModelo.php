<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItemModelo extends Model
{
    public $timestamps = false;

    protected $table = 'checklist_itens_modelo';

    protected $fillable = ['categoria_id', 'label', 'descricao', 'ordem', 'obrigatorio', 'ativo'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategoria::class, 'categoria_id');
    }
}
