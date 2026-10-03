<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaCorrecao extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'auditoria_correcoes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['usuario_id', 'entidade', 'entidade_id', 'acao', 'antes', 'depois', 'ip'];

    protected $casts = ['antes' => 'array', 'depois' => 'array', 'created_at' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Registra uma alteração feita por admin guardando só os campos que mudaram.
     * Em exclusão, guarda o registro inteiro em "antes".
     */
    public static function registrar(Model $model, string $entidade, string $acao, array $antes, array $depois = []): ?self
    {
        if ($acao === 'correcao') {
            $mudou = array_keys(array_filter($depois, fn ($v, $k) => ! self::iguais($antes[$k] ?? null, $v), ARRAY_FILTER_USE_BOTH));
            if (! $mudou) {
                return null;
            }
            $antes = array_intersect_key($antes, array_flip($mudou));
            $depois = array_intersect_key($depois, array_flip($mudou));
        }

        return self::create([
            'usuario_id' => auth()->id(),
            'entidade' => $entidade,
            'entidade_id' => $model->getKey(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois ?: null,
            'ip' => request()->ip(),
        ]);
    }

    // Decimais vêm do banco como string ("5.500"), então compara números pelo valor
    private static function iguais(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a === $b;
    }
}
