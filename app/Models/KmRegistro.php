<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KmRegistro extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'km_registros';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'checkin_id',
        'km_anterior', 'km_atual', 'observacao', 'registrado_at',
    ];

    protected $casts = ['registrado_at' => 'datetime'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }
}
