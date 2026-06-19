<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Escala extends Model {
    use HasUuids;
    protected $table = 'escalas';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['motorista_id','data','turno','veiculo_id','observacao'];
    public function motorista() { return $this->belongsTo(Motorista::class); }
    public function veiculo()   { return $this->belongsTo(Veiculo::class); }
}
