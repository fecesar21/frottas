<?php

namespace App\Http\Requests\Solicitacao;

use App\Models\Localidade;
use App\Models\MotivoViagem;
use App\Models\Unidade;
use App\Rules\MotivoViagemValido;
use App\Rules\PontoViagemExiste;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Origem forçada para a unidade do solicitante (Transferência / Exames), ignorando o que vier do cliente. */
    protected function prepareForValidation(): void
    {
        $unidadeId = $this->user()?->unidade_id;
        if ($unidadeId && MotivoViagem::origemNaUnidadeDoSolicitante($this->input('motivo'))) {
            $this->merge(['origem_tipo' => 'unidade', 'origem_id' => $unidadeId]);
        }
    }

    public function rules(): array
    {
        $comOrigemDestino = 'transferencia_paciente,transporte_colaborador';
        if (MotivoViagem::origemNaUnidadeDoSolicitante($this->input('motivo'))) {
            $comOrigemDestino .= ','.$this->input('motivo');
        }

        return [
            'motivo' => ['required', 'string', new MotivoViagemValido(solicitacao: true)],

            'origem_tipo' => 'required_if:motivo,'.$comOrigemDestino.'|nullable|in:unidade,localidade',
            'origem_id' => ['required_if:motivo,'.$comOrigemDestino, 'nullable', 'uuid', new PontoViagemExiste($this->input('origem_tipo'))],
            'destino_tipo' => 'required_if:motivo,'.$comOrigemDestino.'|nullable|in:unidade,localidade',
            'destino_id' => ['required_if:motivo,'.$comOrigemDestino, 'nullable', 'uuid', new PontoViagemExiste($this->input('destino_tipo'))],
            'numero_atendimento' => 'required_if:motivo,transferencia_paciente|nullable|integer|digits_between:1,6',
            'autorizacao_referencia_em' => 'required_if:motivo,transferencia_paciente|nullable|date|before_or_equal:now',
            'cidade' => 'required_if:motivo,buscar_medico|nullable|string|max:150',
            'hospital_destino' => 'required_if:motivo,material_outro_hospital|nullable|string|max:150',
            'fornecedor_nome' => 'required_if:motivo,buscar_material_fornecedor|nullable|string|max:150',

            'observacoes' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $usuario = $this->user();

            if ($this->input('motivo') === MotivoViagem::TFD && ! MotivoViagem::tfdPermitidoPara($usuario)) {
                $validator->errors()->add('motivo', 'TFD só pode ser solicitado pelo HMA.');
            }

            if (! MotivoViagem::permitidoPorUoNaUpa($usuario, $this->input('motivo'))) {
                $validator->errors()->add('motivo', 'Este motivo não está disponível para a sua UO.');
            }

            $nomePonto = fn (?string $tipo, ?string $id) => match ($tipo) {
                'unidade' => Unidade::whereKey($id)->value('nome'),
                'localidade' => Localidade::whereKey($id)->value('nome'),
                default => null,
            };
            if (MotivoViagem::somenteDestino($nomePonto($this->input('origem_tipo'), $this->input('origem_id')))) {
                $validator->errors()->add('origem_id', 'Este local só pode ser usado como destino.');
            }
            if (MotivoViagem::somenteDestino($nomePonto($this->input('destino_tipo'), $this->input('destino_id')))
                && ! MotivoViagem::ehExames($this->input('motivo'))) {
                $validator->errors()->add('destino_id', 'Este destino só está disponível para Exames - Levar Paciente.');
            }

            if (! $usuario->unidade_id || ! MotivoViagem::origemNaUnidadeDoSolicitante($this->input('motivo'))) {
                return;
            }

            // O destino não pode ser o próprio local do solicitante (mesma unidade ou localidade homônima).
            $destinoId = $this->input('destino_id');
            $mesmoLocal = match ($this->input('destino_tipo')) {
                'unidade' => $destinoId === $usuario->unidade_id,
                'localidade' => self::normalizar(Localidade::whereKey($destinoId)->value('nome')) === self::normalizar($usuario->unidade?->nome),
                default => false,
            };
            if ($mesmoLocal) {
                $validator->errors()->add('destino_id', 'O destino não pode ser a mesma unidade de origem.');
            }

            $destinoNome = match ($this->input('destino_tipo')) {
                'unidade' => Unidade::whereKey($destinoId)->value('nome'),
                'localidade' => Localidade::whereKey($destinoId)->value('nome'),
                default => null,
            };
            if (MotivoViagem::destinoBloqueado($usuario, $this->input('motivo'), $destinoNome)) {
                $validator->errors()->add('destino_id', 'Este destino não está disponível para este motivo na sua unidade.');
            }
        }];
    }

    private static function normalizar(?string $nome): string
    {
        return Str::of((string) $nome)->ascii()->lower()->squish()->toString();
    }
}
