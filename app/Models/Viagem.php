<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Viagem extends Model {
    use HasUuids;
    protected $table = 'viagens';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'veiculo_id','motorista_id','checkin_id',
        'origem','destino','km_saida','km_chegada',
        'saida_at','chegada_at','status','observacoes',
    ];
    protected $casts = ['saida_at'=>'datetime','chegada_at'=>'datetime'];
    public function veiculo()   { return $this->belongsTo(Veiculo::class); }
    public function motorista() { return $this->belongsTo(Motorista::class); }
}
