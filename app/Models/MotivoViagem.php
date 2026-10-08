<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MotivoViagem extends Model
{
    use HasFactory, HasUuids;

    public const TIPO_ADMINISTRATIVO = 'administrativo';

    public const TIPO_AMBULANCIA = 'ambulancia';

    public const TIPO_AMBOS = 'ambos';

    protected $table = 'motivos_viagem';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['codigo', 'nome', 'tipo_veiculo', 'disponivel_solicitacao', 'ativo'];

    protected $attributes = ['ativo' => true, 'disponivel_solicitacao' => false, 'sistema' => false];

    protected $casts = ['disponivel_solicitacao' => 'boolean', 'ativo' => 'boolean', 'sistema' => 'boolean'];

    /** @var array<string, ?MotivoViagem>|null */
    private static ?array $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::limparCache());
        static::deleted(fn () => self::limparCache());
    }

    /** Descarta o cache estático de motivos (por requisição). */
    public static function limparCache(): void
    {
        self::$cache = null;
    }

    public static function porCodigo(?string $codigo): ?self
    {
        self::$cache ??= self::all()->keyBy('codigo')->all();

        return self::$cache[trim((string) $codigo)] ?? null;
    }

    /** Rótulo de exibição; cai para o código "titulado" se o motivo não existir mais. */
    public static function rotulo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 'Não informado';
        }

        return self::porCodigo($codigo)?->nome ?? Str::title(str_replace('_', ' ', $codigo));
    }

    /** Gera um código único (slug com sufixo numérico em caso de colisão). */
    public static function gerarCodigo(string $nome): string
    {
        $base = Str::limit(Str::slug($nome, '_'), 70, '') ?: 'motivo';
        $codigo = $base;
        for ($i = 2; self::where('codigo', $codigo)->exists(); $i++) {
            $codigo = "{$base}_{$i}";
        }

        return $codigo;
    }

    public function permiteVeiculo(?Veiculo $veiculo): bool
    {
        if (! $veiculo || $this->tipo_veiculo === self::TIPO_AMBOS) {
            return true;
        }

        return $veiculo->ehAmbulancia() === ($this->tipo_veiculo === self::TIPO_AMBULANCIA);
    }

    public function emUso(): bool
    {
        return DB::table('viagens')->where('motivo_viagem', $this->codigo)->exists()
            || DB::table('solicitacoes')->where('motivo', $this->codigo)->exists();
    }

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('ativo', true);
    }

    public function scopeParaSolicitacao(Builder $q): Builder
    {
        return $q->where('disponivel_solicitacao', true);
    }
}
