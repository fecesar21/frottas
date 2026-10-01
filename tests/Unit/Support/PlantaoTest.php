<?php

namespace Tests\Unit\Support;

use App\Support\Plantao;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlantaoTest extends TestCase
{
    public static function horarios(): array
    {
        return [
            '00:00 é noturno do dia anterior' => ['2026-10-02 00:00:00', '2026-10-01', 'noturno'],
            '06:59 é noturno do dia anterior' => ['2026-10-02 06:59:59', '2026-10-01', 'noturno'],
            '07:00 inicia o diurno' => ['2026-10-02 07:00:00', '2026-10-02', 'diurno'],
            '18:59 ainda é diurno' => ['2026-10-02 18:59:59', '2026-10-02', 'diurno'],
            '19:00 inicia o noturno' => ['2026-10-02 19:00:00', '2026-10-02', 'noturno'],
            '23:59 é noturno do mesmo dia' => ['2026-10-02 23:59:59', '2026-10-02', 'noturno'],
        ];
    }

    #[DataProvider('horarios')]
    public function test_identifica_o_plantao(string $agora, string $data, string $turno): void
    {
        $this->assertSame(['data' => $data, 'turno' => $turno], Plantao::atual(Carbon::parse($agora)));
    }
}
