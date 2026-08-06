<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadeAdMapeamento extends Model
{
    use HasUuids;

    protected $table = 'unidade_ad_mapeamentos';

    protected $fillable = ['valor_ad', 'unidade_id'];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
