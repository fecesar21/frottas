<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChecklistResposta extends Model {
    use HasUuids;
    protected $table = 'checklist_respostas';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['passagem_id','item_modelo_id','resultado','observacao'];
    public function passagem()   { return $this->belongsTo(PassagemPlantao::class,'passagem_id'); }
    public function itemModelo() { return $this->belongsTo(ChecklistItemModelo::class,'item_modelo_id'); }
}
