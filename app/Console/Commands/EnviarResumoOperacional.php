<?php

namespace App\Console\Commands;

use App\Mail\ResumoOperacionalMail;
use App\Models\Usuario;
use App\Services\ResumoOperacionalService;
use App\Support\Plantao;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EnviarResumoOperacional extends Command
{
    protected $signature = 'relatorio:resumo
        {tipo : plantao|mensal}
        {--data= : Plantão: data de início (Y-m-d). Mensal: qualquer dia do mês (Y-m-d)}
        {--turno= : Plantão: diurno|noturno}
        {--dry-run : Salva o PDF em storage/app/relatorios sem enviar e-mail}';

    protected $description = 'Envia por e-mail aos admins/gestores o resumo em PDF do último plantão ou do mês anterior';

    public function handle(ResumoOperacionalService $service): int
    {
        $tipo = $this->argument('tipo');

        if ($tipo === 'plantao') {
            [$inicio, $fim, $titulo, $arquivo] = $this->janelaPlantao();
        } elseif ($tipo === 'mensal') {
            [$inicio, $fim, $titulo, $arquivo] = $this->janelaMensal();
        } else {
            $this->error('Tipo inválido: use plantao ou mensal.');

            return self::INVALID;
        }

        $dados = $service->gerar($inicio, $fim) + [
            'titulo' => $titulo,
            'periodo' => $inicio->format('d/m/Y H:i').' a '.$fim->format('d/m/Y H:i'),
        ];

        if ($this->option('dry-run')) {
            Storage::put("relatorios/{$arquivo}", ResumoOperacionalMail::pdf($dados));
            $this->info('PDF salvo em '.Storage::path("relatorios/{$arquivo}"));

            return self::SUCCESS;
        }

        $destinatarios = Usuario::whereIn('perfil', ['admin', 'gestor'])
            ->where('ativo', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->unique();

        if ($destinatarios->isEmpty()) {
            $this->warn('Nenhum admin/gestor ativo com e-mail cadastrado.');
            logger()->warning("relatorio:resumo {$tipo}: nenhum destinatário com e-mail.");

            return self::SUCCESS;
        }

        foreach ($destinatarios as $email) {
            Mail::to($email)->queue(new ResumoOperacionalMail($dados, $arquivo));
        }

        $this->info("{$titulo}: enviado para {$destinatarios->count()} destinatário(s).");

        return self::SUCCESS;
    }

    /** Plantão informado por opções ou, por padrão, o que terminou há pouco. */
    private function janelaPlantao(): array
    {
        $diurno = config('plantao.inicio_diurno', '07:00');
        $noturno = config('plantao.inicio_noturno', '19:00');

        if ($this->option('data') && $this->option('turno')) {
            $plantao = ['data' => $this->option('data'), 'turno' => $this->option('turno')];
        } else {
            // Roda 1h após o fim: o plantão encerrado é o que vigorava 2h atrás.
            $plantao = Plantao::atual(now()->subHours(2));
        }

        if ($plantao['turno'] === 'diurno') {
            $inicio = Carbon::parse("{$plantao['data']} {$diurno}");
            $fim = Carbon::parse("{$plantao['data']} {$noturno}")->subSecond();
        } else {
            $inicio = Carbon::parse("{$plantao['data']} {$noturno}");
            $fim = Carbon::parse("{$plantao['data']} {$diurno}")->addDay()->subSecond();
        }

        $rotulo = $plantao['turno'] === 'diurno' ? 'Diurno' : 'Noturno';

        return [
            $inicio, $fim,
            "Resumo do Plantão {$rotulo} de {$inicio->format('d/m/Y')}",
            "resumo-plantao-{$plantao['turno']}-{$inicio->format('Y-m-d')}.pdf",
        ];
    }

    private function janelaMensal(): array
    {
        $ref = $this->option('data') ? Carbon::parse($this->option('data')) : now()->subMonthNoOverflow();
        $inicio = $ref->copy()->startOfMonth();
        $fim = $ref->copy()->endOfMonth();

        return [
            $inicio, $fim,
            'Resumo Mensal de '.$inicio->format('m/Y'),
            "resumo-mensal-{$inicio->format('Y-m')}.pdf",
        ];
    }
}
