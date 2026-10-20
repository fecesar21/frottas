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

    /** Motivo automático da viagem de retorno à origem: não é selecionável nem editável. */
    public const RETORNO = 'retorno';

    public const TFD = 'tfd';

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

    public function ehRetorno(): bool
    {
        return $this->codigo === self::RETORNO;
    }

    /**
     * Motivos de solicitação cuja origem é sempre a unidade do solicitante:
     * Transferência de Paciente e "Exames - Levar Paciente" (cadastrado pelo
     * admin, por isso reconhecido pelo prefixo do código ou do nome).
     */
    public static function origemNaUnidadeDoSolicitante(?string $codigo): bool
    {
        return $codigo === 'transferencia_paciente' || self::ehExames($codigo);
    }

    /** Motivos em que, ao chegar ao destino, o motorista pode gerar a viagem de retorno à origem. */
    public static function permiteRetornoOrigem(?string $codigo): bool
    {
        return in_array($codigo, ['transferencia_paciente', self::TFD], true) || self::ehExames($codigo);
    }

    /** "Exames - Levar Paciente": pelo código ou, como o código é imutável, pelo nome (motivo renomeado). */
    public static function ehExames(?string $codigo): bool
    {
        if (! $codigo) {
            return false;
        }

        return Str::startsWith($codigo, 'exames')
            || Str::startsWith(Str::of(self::rotulo($codigo))->ascii()->lower()->toString(), 'exames');
    }

    /** TFD só pode ser solicitado pelo HMA (unidade com "HMA" no nome); as demais, como a UPA, não o enxergam. */
    public static function tfdPermitidoPara(Usuario $usuario): bool
    {
        return ! $usuario->unidade_id || preg_match('/\bHMA\b/i', (string) $usuario->unidade?->nome) === 1;
    }

    /** Destinos que a Transferência de Paciente não pode ter, por unidade de origem (palavra no nome da unidade). */
    public const DESTINOS_BLOQUEADOS_TRANSFERENCIA = [
        'UPA' => ['IMED', 'ITPAC', 'AME', 'PAI'],
        'PAI' => ['IMED', 'ITPAC', 'UPA', 'AME'],
    ];

    /** Únicos destinos permitidos em "Exames - Levar Paciente", por unidade de origem (palavra no nome da unidade). */
    public const DESTINOS_PERMITIDOS_EXAMES = [
        'UPA' => ['HMA', 'HMDO', 'IMED', 'ITPAC'],
        'PAI' => ['HMA', 'HMDO', 'IMED', 'ITPAC'],
    ];

    /** O motivo, partindo da unidade do solicitante, não pode ir para o destino com este nome. */
    public static function destinoBloqueado(Usuario $usuario, ?string $codigo, ?string $destinoNome): bool
    {
        $destino = Str::of((string) $destinoNome)->ascii()->upper()->squish()->toString();
        $daOrigem = fn (string $origem) => preg_match('/\b'.$origem.'\b/i', (string) $usuario->unidade?->nome) === 1;

        if ($codigo === 'transferencia_paciente') {
            foreach (self::DESTINOS_BLOQUEADOS_TRANSFERENCIA as $origem => $bloqueados) {
                if ($daOrigem($origem) && in_array($destino, $bloqueados, true)) {
                    return true;
                }
            }
        } elseif (self::ehExames($codigo)) {
            foreach (self::DESTINOS_PERMITIDOS_EXAMES as $origem => $permitidos) {
                if ($daOrigem($origem) && ! in_array($destino, $permitidos, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** UOs (departamento do AD) da UPA que só solicitam Transferência de Paciente e Exames. */
    public const UOS_ASSISTENCIAIS_UPA = ['enfermagem', 'pronto atendimento'];

    /**
     * Na UPA, a UO do solicitante define os motivos: Enfermagem/Pronto Atendimento
     * só veem Transferência e Exames; as demais UOs veem todos, exceto esses.
     * Fora da UPA, ou sem departamento conhecido no AD, não há restrição.
     */
    public static function permitidoPorUoNaUpa(Usuario $usuario, ?string $codigo): bool
    {
        if (preg_match('/\bUPA\b/i', (string) $usuario->unidade?->nome) !== 1 || ! $usuario->ldap_guid) {
            return true;
        }
        $departamento = Colaborador::where('ldap_guid', $usuario->ldap_guid)->value('departamento');
        if (blank($departamento)) {
            return true;
        }
        $assistencial = in_array(Str::of($departamento)->ascii()->lower()->squish()->toString(), self::UOS_ASSISTENCIAIS_UPA, true);

        return $assistencial === self::origemNaUnidadeDoSolicitante($codigo);
    }

    public function scopeSelecionaveis(Builder $q): Builder
    {
        return $q->where('codigo', '!=', self::RETORNO);
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
