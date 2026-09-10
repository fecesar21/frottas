<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Localidade extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'localidades';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['nome', 'endereco', 'latitude', 'longitude', 'telefone', 'email', 'ativo'];

    protected $attributes = [
        'ativo' => true,
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'ativo' => 'boolean',
    ];
}
