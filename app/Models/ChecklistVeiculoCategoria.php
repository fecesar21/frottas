<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistVeiculoCategoria extends Model
{
    protected $table = 'checklist_veiculo_categorias';

    protected $fillable = ['nome', 'ordem', 'ativo'];

    public function itens(): HasMany
    {
        return $this->hasMany(ChecklistVeiculoItemModelo::class, 'categoria_id');
    }
}
