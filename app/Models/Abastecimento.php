<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abastecimento extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $timestamps = false;

    protected $table = 'abastecimentos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'checkin_id', 'posto', 'combustivel',
        'litros', 'valor_litro', 'km_momento', 'abastecido_at', 'nota_fiscal', 'comprovante_path', 'observacoes',
    ];

    protected $casts = ['abastecido_at' => 'datetime'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }
}
