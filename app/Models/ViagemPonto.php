<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ViagemPonto extends Model
{
    use HasUuids;

    protected $table = 'viagem_pontos';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'viagem_id', 'latitude', 'longitude', 'accuracy', 'capturado_at',
    ];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'accuracy'     => 'float',
        'capturado_at' => 'datetime',
    ];

    public function viagem()
    {
        return $this->belongsTo(Viagem::class);
    }
}
