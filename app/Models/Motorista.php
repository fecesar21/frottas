<?php
// ============================================================
//  app/Models/Motorista.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Motorista extends Model {
    use HasFactory, HasUuids;
    protected $table = 'motoristas';
    public $incrementing = false; protected $keyType = 'string';
    protected $fillable = [
        'nome','cpf','telefone','email','cnh_numero','cnh_categoria',
        'cnh_validade','turno_padrao','status','observacoes',
    ];
    public function usuario(): HasOne   { return $this->hasOne(Usuario::class, 'motorista_id'); }
    public function checkinAtivo(): HasOne  { return $this->hasOne(Checkin::class,'motorista_id')->where('status','ativo'); }
    public function checkins(): HasMany      { return $this->hasMany(Checkin::class,'motorista_id'); }
    public function escalas(): HasMany       { return $this->hasMany(Escala::class,'motorista_id'); }
    public function viagens(): HasMany       { return $this->hasMany(Viagem::class,'motorista_id'); }
    public function abastecimentos(): HasMany{ return $this->hasMany(Abastecimento::class,'motorista_id'); }
    public function unidades(): BelongsToMany { return $this->belongsToMany(Unidade::class, 'motorista_unidade'); }
    public function getDiasParaVencerCnhAttribute() {
        return now()->diffInDays($this->cnh_validade, false);
    }
}
