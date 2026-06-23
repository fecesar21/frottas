<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Veiculo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'veiculos';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'placa', 'modelo', 'marca', 'ano', 'cor', 'chassi', 'renavam',
        'combustivel', 'capacidade_tanque', 'km_atual', 'km_proxima_revisao',
        'status', 'observacoes',
    ];

    // Relações
    public function checkinAtivo()
    {
        return $this->hasOne(Checkin::class, 'veiculo_id')->where('status', 'ativo');
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class, 'veiculo_id');
    }

    public function kmRegistros()
    {
        return $this->hasMany(KmRegistro::class, 'veiculo_id');
    }

    public function abastecimentos()
    {
        return $this->hasMany(Abastecimento::class, 'veiculo_id');
    }

    public function viagens()
    {
        return $this->hasMany(Viagem::class, 'veiculo_id');
    }
}
