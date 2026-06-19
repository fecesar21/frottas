<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Abastecimento extends Model {
    use HasUuids;
    public $timestamps = false;
    protected $table = 'abastecimentos';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'veiculo_id','motorista_id','checkin_id','posto','combustivel',
        'litros','valor_litro','km_momento','abastecido_at','nota_fiscal','observacoes',
    ];
    protected $casts = ['abastecido_at'=>'datetime'];
    public function veiculo()   { return $this->belongsTo(Veiculo::class); }
    public function motorista() { return $this->belongsTo(Motorista::class); }
}
