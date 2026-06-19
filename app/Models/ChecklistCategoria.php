<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChecklistCategoria extends Model {
    public $timestamps = false;
    protected $table = 'checklist_categorias';
    protected $fillable = ['nome','ordem','ativo'];
    public function itens() { return $this->hasMany(ChecklistItemModelo::class,'categoria_id'); }
}
