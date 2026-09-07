<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistCategoria extends Model
{
    public $timestamps = false;

    protected $table = 'checklist_categorias';

    protected $fillable = ['nome', 'ordem', 'ativo'];

    public function itens(): HasMany
    {
        return $this->hasMany(ChecklistItemModelo::class, 'categoria_id');
    }
}
