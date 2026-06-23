<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Checkin extends Model {
    use HasFactory, HasUuids;
    protected $table = 'checkins';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'motorista_id','veiculo_id','escala_id','turno',
        'km_saida','km_retorno','nivel_combustivel_saida','nivel_combustivel_retorno',
        'checkin_at','checkout_at','status','ocorrencias',
    ];
    protected $casts = ['checkin_at'=>'datetime','checkout_at'=>'datetime'];
    public function motorista() { return $this->belongsTo(Motorista::class); }
    public function veiculo()   { return $this->belongsTo(Veiculo::class); }
    public function escala()    { return $this->belongsTo(Escala::class); }
}
