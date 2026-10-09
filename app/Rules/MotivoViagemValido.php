<?php

namespace App\Rules;

use App\Models\MotivoViagem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MotivoViagemValido implements ValidationRule
{
    /**
     * @param  bool  $solicitacao  exige que o motivo esteja disponível para solicitações
     * @param  string|null  $aceitarInativo  código já gravado na viagem, aceito mesmo se inativado depois
     */
    public function __construct(private bool $solicitacao = false, private ?string $aceitarInativo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $motivo = MotivoViagem::porCodigo(is_string($value) ? $value : null);
        $mantido = $this->aceitarInativo !== null && $value === $this->aceitarInativo;

        if (! $motivo || (! $motivo->ativo && ! $mantido) || ($motivo->ehRetorno() && ! $mantido) || ($this->solicitacao && ! $motivo->disponivel_solicitacao)) {
            $fail('Motivo de viagem inválido ou inativo.');
        }
    }
}
