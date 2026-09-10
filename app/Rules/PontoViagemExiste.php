<?php

namespace App\Rules;

use App\Models\Localidade;
use App\Models\Unidade;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PontoViagemExiste implements ValidationRule
{
    public function __construct(private ?string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->tipo || ! $value) {
            return;
        }

        $existe = match ($this->tipo) {
            'unidade' => Unidade::whereKey($value)->exists(),
            'localidade' => Localidade::whereKey($value)->exists(),
            default => false,
        };

        if (! $existe) {
            $fail("O :attribute selecionado não existe como {$this->tipo}.");
        }
    }
}
