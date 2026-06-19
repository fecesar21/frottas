<?php
// ============================================================
//  app/Models/Motorista.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Motorista extends Model {
    use HasUuids;
    protected $table = 'motoristas';
    public $incrementing = false; protected $keyType = 'string';
    protected $fillable = [
        'nome','cpf','telefone','email','cnh_numero','cnh_categoria',
        'cnh_validade','turno_padrao','status','observacoes',
    ];
    public function checkinAtivo()  { return $this->hasOne(Checkin::class,'motorista_id')->where('status','ativo'); }
    public function checkins()      { return $this->hasMany(Checkin::class,'motorista_id'); }
    public function escalas()       { return $this->hasMany(Escala::class,'motorista_id'); }
    public function viagens()       { return $this->hasMany(Viagem::class,'motorista_id'); }
    public function abastecimentos(){ return $this->hasMany(Abastecimento::class,'motorista_id'); }
    public function getDiasParaVencerCnhAttribute() {
        return now()->diffInDays($this->cnh_validade, false);
    }
}
