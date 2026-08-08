<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistVeiculoResposta extends Model
{
    use HasUuids;

    protected $table = 'checklist_veiculo_respostas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['checklist_veiculo_id', 'item_modelo_id', 'conforme', 'observacao', 'foto_path'];

    protected $casts = [
        'conforme' => 'boolean',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ChecklistVeiculo::class, 'checklist_veiculo_id');
    }

    public function itemModelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistVeiculoItemModelo::class, 'item_modelo_id');
    }
}
