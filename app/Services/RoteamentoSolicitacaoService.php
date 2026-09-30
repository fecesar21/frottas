<?php

namespace App\Services;

use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Unidade;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decide quais motoristas em atividade (check-in ativo) devem ser avisados de
 * uma nova solicitação e, portanto, podem assumi-la. Regras em config/solicitacao.php.
 */
class RoteamentoSolicitacaoService
{
    /**
     * @return Collection<int, Motorista>
     */
    public function motoristasElegiveis(Solicitacao $solicitacao): Collection
    {
        $regra = $this->regra($solicitacao);
        if ($regra === null) {
            return collect();
        }

        return Motorista::whereHas('checkinAtivo')
            ->with(['usuario', 'unidades', 'checkinAtivo.veiculo.unidades'])
            ->get()
            ->filter(fn (Motorista $m) => $this->atende($m, $regra))
            ->values();
    }

    public function podeAssumir(Solicitacao $solicitacao, Motorista $motorista): bool
    {
        $regra = $this->regra($solicitacao);
        if ($regra === null) {
            return false;
        }

        $motorista->loadMissing(['unidades', 'checkinAtivo.veiculo.unidades']);

        return $this->atende($motorista, $regra);
    }

    /**
     * @return array{modelos: array<int, string>, unidade_id: ?string}|null
     */
    private function regra(Solicitacao $solicitacao): ?array
    {
        $config = config("solicitacao.roteamento_motoristas.{$solicitacao->motivo}");
        if (! $config) {
            return null;
        }

        if (isset($config['modelos'])) {
            return ['modelos' => $config['modelos'], 'unidade_id' => null];
        }

        $unidade = $solicitacao->unidade_id ? Unidade::find($solicitacao->unidade_id) : null;
        if (! $unidade) {
            return null;
        }

        foreach ($config['por_unidade'] ?? [] as $chave => $modelos) {
            if ($this->contem($unidade->nome, $chave)) {
                return ['modelos' => $modelos, 'unidade_id' => $unidade->id];
            }
        }

        return null;
    }

    private function atende(Motorista $motorista, array $regra): bool
    {
        $veiculo = $motorista->checkinAtivo?->veiculo;
        if (! $veiculo) {
            return false;
        }

        $modeloOk = collect($regra['modelos'])->contains(fn ($m) => $this->contem($veiculo->modelo, $m));
        if (! $modeloOk) {
            return false;
        }

        if ($regra['unidade_id'] === null) {
            return true;
        }

        return $motorista->unidades->contains('id', $regra['unidade_id'])
            && $veiculo->unidades->contains('id', $regra['unidade_id']);
    }

    private function contem(?string $texto, string $trecho): bool
    {
        return $texto !== null && str_contains($this->normalizar($texto), $this->normalizar($trecho));
    }

    private function normalizar(string $texto): string
    {
        return preg_replace('/\s+/', ' ', mb_strtoupper(Str::ascii($texto)));
    }
}
