<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Limpa o cache dos KPIs do dashboard quando o model muda, para que
 * contagens como "veículos em manutenção" reflitam a alteração na hora
 * em vez de esperar o TTL de 5 minutos do cache.
 */
trait InvalidaCacheDashboard
{
    public const CACHE_DASHBOARD = 'relatorio.dashboard';

    public static function bootInvalidaCacheDashboard(): void
    {
        $limpar = fn () => Cache::forget(self::CACHE_DASHBOARD);

        static::saved($limpar);
        static::deleted($limpar);
    }
}
