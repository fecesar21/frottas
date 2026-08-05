# Notificação de Nova Solicitação de Transporte — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Quando um operador cria uma solicitação de transporte, admins e gestores da unidade são avisados por notificação in-app (sino piscando + bipe + toast) e por e-mail.

**Architecture:** O backend já tem infraestrutura de notificações Laravel (tabela `notifications`, trait `Notifiable` em `Usuario`, `NotificacaoController@index`/`marcarLida`, e duas notificações de exemplo — `CnhVencendoNotification`, `ManutencaoPendenteNotification`). Vamos seguir exatamente esse padrão: criar `NovaSolicitacaoTransporte extends Notification` (canais `mail` + `database`), disparar em `SolicitacaoService::store()` para admins + gestores da unidade, e adicionar dois endpoints novos ao `NotificacaoController` (contagem/lista de não lidas, marcar todas como lidas). No frontend, um hook de polling (`useNotificacoes`) alimenta um sino no `Header` com badge, animação de piscar, bipe sintetizado via `AudioContext` e toast; a tela de Solicitações marca tudo como lido ao montar.

**Tech Stack:** Laravel 11 (Notifications, Queue), React 18 + @tanstack/react-query, Tailwind.

## Global Constraints

- Canais da notificação: `['database', 'mail']`, classe deve `use Queueable` (mesmo padrão de `ManutencaoPendenteNotification`).
- Destinatários: usuários com `perfil = 'admin'` (todas as unidades) UNIÃO usuários com `perfil = 'gestor'` E `unidade_id` igual ao da solicitação.
- Endpoints novos ficam dentro do grupo `auth:sanctum` já existente em `routes/api.php`, ao lado das rotas de `notificacoes` já registradas.
- Frontend: marcar como lida ocorre APENAS ao montar a tela `/solicitacoes` (`SolicitacoesList`), nunca ao abrir o sino.
- Polling do hook: intervalo de 20000ms (20s).
- Beep: gerado via Web Audio API (`AudioContext` + `OscillatorNode`), sem arquivo de áudio externo.
- Sem WebSockets/broadcast — fora de escopo.

---

### Task 1: Notification `NovaSolicitacaoTransporte`

**Files:**
- Create: `app/Notifications/NovaSolicitacaoTransporte.php`
- Test: `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (adicionar teste ao arquivo existente)

**Interfaces:**
- Consumes: `App\Models\Solicitacao` (propriedades `motivo`, `origem_unidade_id`/`destino_unidade_id` via relations `origemUnidade`/`destinoUnidade`, `cidade`, `hospital_destino`, `usuario` relation).
- Produces: `App\Notifications\NovaSolicitacaoTransporte` — construtor `__construct(Solicitacao $solicitacao)`, usado pela Task 2.

- [ ] **Step 1: Escrever o teste que falha**

Adicionar ao final da classe em `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (verificar o `use` no topo do arquivo e adicionar `use App\Notifications\NovaSolicitacaoTransporte;` e `use App\Models\Usuario;` e `use Illuminate\Support\Facades\Notification;` se ainda não existirem):

```php
public function test_cria_solicitacao_notifica_admins_e_gestor_da_unidade(): void
{
    Notification::fake();

    $unidade = \App\Models\Unidade::factory()->create();
    $operador = Usuario::factory()->create(['perfil' => 'operador', 'unidade_id' => $unidade->id]);
    $adminGlobal = Usuario::factory()->admin()->create();
    $gestorMesmaUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => $unidade->id]);
    $gestorOutraUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => \App\Models\Unidade::factory()->create()->id]);

    $token = $operador->createToken('test')->plainTextToken;
    $this->withToken($token);

    $this->postJson('/api/solicitacoes', [
        'motivo' => 'tfd',
        'cidade' => 'Curitiba',
    ])->assertCreated();

    Notification::assertSentTo($adminGlobal, NovaSolicitacaoTransporte::class);
    Notification::assertSentTo($gestorMesmaUnidade, NovaSolicitacaoTransporte::class);
    Notification::assertNotSentTo($gestorOutraUnidade, NovaSolicitacaoTransporte::class);
    Notification::assertNotSentTo($operador, NovaSolicitacaoTransporte::class);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter test_cria_solicitacao_notifica_admins_e_gestor_da_unidade`
Expected: FAIL — `Class "App\Notifications\NovaSolicitacaoTransporte" not found` (a classe ainda não existe).

- [ ] **Step 3: Criar a classe de notificação**

```php
<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NovaSolicitacaoTransporte extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $detalhe = $this->detalhe();

        return (new MailMessage)
            ->subject('Nova solicitação de transporte')
            ->greeting('Nova solicitação recebida')
            ->line("Solicitante: {$this->solicitacao->usuario?->nome}")
            ->line("Motivo: {$this->solicitacao->motivo}")
            ->line("Detalhe: {$detalhe}")
            ->action('Ver solicitações', url('/solicitacoes'))
            ->line('Acesse o sistema para aceitar ou tratar essa solicitação.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'solicitacao_id' => $this->solicitacao->id,
            'motivo' => $this->solicitacao->motivo,
            'detalhe' => $this->detalhe(),
            'solicitante_nome' => $this->solicitacao->usuario?->nome,
            'unidade_id' => $this->solicitacao->unidade_id,
        ];
    }

    private function detalhe(): string
    {
        $this->solicitacao->loadMissing(['origemUnidade', 'destinoUnidade']);

        if ($this->solicitacao->origemUnidade || $this->solicitacao->destinoUnidade) {
            return ($this->solicitacao->origemUnidade?->nome ?? '—').' → '.($this->solicitacao->destinoUnidade?->nome ?? '—');
        }

        return $this->solicitacao->cidade ?? $this->solicitacao->hospital_destino ?? 'Sem detalhe';
    }
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter test_cria_solicitacao_notifica_admins_e_gestor_da_unidade`
Expected: ainda vai FALHAR nesse ponto, porque `SolicitacaoService::store()` ainda não dispara a notificação — isso é esperado, a Task 2 resolve. Confirme que a falha agora é `Notification::assertSentTo` (não mais "class not found").

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/NovaSolicitacaoTransporte.php tests/Feature/Solicitacao/SolicitacaoApiTest.php
git commit -m "test: add failing test for nova solicitacao notification"
```

---

### Task 2: Disparar a notificação em `SolicitacaoService::store()`

**Files:**
- Modify: `app/Services/SolicitacaoService.php`

**Interfaces:**
- Consumes: `App\Notifications\NovaSolicitacaoTransporte` (Task 1), `App\Models\Usuario`.
- Produces: nenhuma interface nova — comportamento observável via o teste da Task 1.

- [ ] **Step 1: Rodar o teste da Task 1 novamente para confirmar o estado atual**

Run: `php artisan test --filter test_cria_solicitacao_notifica_admins_e_gestor_da_unidade`
Expected: FAIL em `Notification::assertSentTo`.

- [ ] **Step 2: Implementar o disparo em `store()`**

Editar `app/Services/SolicitacaoService.php`. Adicionar aos `use`:

```php
use App\Notifications\NovaSolicitacaoTransporte;
use Illuminate\Support\Facades\Notification;
```

Substituir o método `store()`:

```php
public function store(array $data, Usuario $usuario): Solicitacao
{
    $data['usuario_id'] = $usuario->id;
    $data['unidade_id'] = $usuario->unidade_id;
    $data['status'] = 'aberto';

    $solicitacao = Solicitacao::create($data);

    $destinatarios = Usuario::where('perfil', 'admin')
        ->orWhere(fn ($q) => $q->where('perfil', 'gestor')->where('unidade_id', $solicitacao->unidade_id))
        ->get();

    Notification::send($destinatarios, new NovaSolicitacaoTransporte($solicitacao));

    return $solicitacao;
}
```

- [ ] **Step 3: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter test_cria_solicitacao_notifica_admins_e_gestor_da_unidade`
Expected: PASS

- [ ] **Step 4: Rodar toda a suíte de Solicitacao para checar regressão**

Run: `php artisan test tests/Feature/Solicitacao`
Expected: todos PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/SolicitacaoService.php
git commit -m "feat: notify admins and gestores on new solicitacao"
```

---

### Task 3: Endpoints de não lidas e marcar-todas-lidas

**Files:**
- Modify: `app/Http/Controllers/Api/NotificacaoController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Notificacao/NotificacaoApiTest.php`

**Interfaces:**
- Produces: `GET /api/notificacoes/nao-lidas` → `{ total: int, notificacoes: [{ id, type, data, created_at }] }` (até 20, mais recentes primeiro); `POST /api/notificacoes/marcar-lidas` → `204 No Content`. Consumido pelo hook `useNotificacoes` na Task 5.

- [ ] **Step 1: Escrever os testes que falham**

Adicionar ao final de `tests/Feature/Notificacao/NotificacaoApiTest.php`:

```php
public function test_lista_notificacoes_nao_lidas_com_total(): void
{
    $usuario = $this->loginAdmin();
    $motorista = Motorista::factory()->cnhVencendo()->create();

    $usuario->notify(new CnhVencendoNotification($motorista));
    $usuario->notify(new CnhVencendoNotification($motorista));

    $this->getJson('/api/notificacoes/nao-lidas')
        ->assertOk()
        ->assertJson(['total' => 2])
        ->assertJsonCount(2, 'notificacoes');
}

public function test_marca_todas_notificacoes_como_lidas(): void
{
    $usuario = $this->loginAdmin();
    $motorista = Motorista::factory()->cnhVencendo()->create();

    $usuario->notify(new CnhVencendoNotification($motorista));
    $usuario->notify(new CnhVencendoNotification($motorista));

    $this->postJson('/api/notificacoes/marcar-lidas')->assertNoContent();

    $this->assertSame(0, $usuario->unreadNotifications()->count());
}
```

- [ ] **Step 2: Rodar os testes e confirmar que falham**

Run: `php artisan test --filter "test_lista_notificacoes_nao_lidas_com_total|test_marca_todas_notificacoes_como_lidas"`
Expected: FAIL com 404 (rota não existe).

- [ ] **Step 3: Implementar no controller**

Editar `app/Http/Controllers/Api/NotificacaoController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(15);
    }

    public function naoLidas(Request $request)
    {
        $notificacoes = $request->user()->unreadNotifications()->latest()->limit(20)->get();

        return response()->json([
            'total' => $notificacoes->count(),
            'notificacoes' => $notificacoes,
        ]);
    }

    public function marcarLida(string $id)
    {
        $notificacao = auth()->user()->notifications()->findOrFail($id);
        $notificacao->markAsRead();

        return response()->json(['message' => 'Notificação marcada como lida']);
    }

    public function marcarTodasLidas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}
```

- [ ] **Step 4: Registrar as rotas**

Em `routes/api.php`, localizar o bloco:

```php
    // Notificações
    Route::get('notificacoes', [NotificacaoController::class, 'index']);
    Route::patch('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida']);
```

Substituir por:

```php
    // Notificações
    Route::get('notificacoes', [NotificacaoController::class, 'index']);
    Route::get('notificacoes/nao-lidas', [NotificacaoController::class, 'naoLidas']);
    Route::post('notificacoes/marcar-lidas', [NotificacaoController::class, 'marcarTodasLidas']);
    Route::patch('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida']);
```

(A rota `notificacoes/nao-lidas` deve vir antes de qualquer rota com `{id}` genérico, o que já é o caso aqui já que `{id}/lida` exige o sufixo `/lida`.)

- [ ] **Step 5: Rodar os testes e confirmar que passam**

Run: `php artisan test --filter "test_lista_notificacoes_nao_lidas_com_total|test_marca_todas_notificacoes_como_lidas"`
Expected: PASS

- [ ] **Step 6: Rodar toda a suíte de Notificacao para checar regressão**

Run: `php artisan test tests/Feature/Notificacao`
Expected: todos PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/NotificacaoController.php routes/api.php tests/Feature/Notificacao/NotificacaoApiTest.php
git commit -m "feat: add unread-notifications and mark-all-read endpoints"
```

---

### Task 4: Cliente API e hook `useNotificacoes` no frontend

**Files:**
- Create: `resources/js/api/notificacoes.js`
- Create: `resources/js/hooks/useNotificacoes.js`

**Interfaces:**
- Consumes: `GET /api/notificacoes/nao-lidas`, `POST /api/notificacoes/marcar-lidas` (Task 3); `useAuth()` de `resources/js/contexts/AuthContext` (propriedade `user.perfil`).
- Produces: `useNotificacoes()` → `{ total: number, notificacoes: Array<{id, type, data, created_at}>, marcarTodasLidas: () => Promise<void> }`. Consumido pela Task 5 (sino no Header) e Task 6 (SolicitacoesList).

- [ ] **Step 1: Criar o cliente da API**

`resources/js/api/notificacoes.js`:

```js
import api from './axios'

export const naoLidas = () => api.get('/notificacoes/nao-lidas')
export const marcarTodasLidas = () => api.post('/notificacoes/marcar-lidas')
```

- [ ] **Step 2: Criar o hook de beep sintetizado**

`resources/js/hooks/useNotificacoes.js` (arquivo único — o beep é pequeno o bastante para não justificar outro arquivo):

```js
import { useEffect, useRef } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../contexts/AuthContext'
import * as notificacoesApi from '../api/notificacoes'

function tocarBeep() {
  try {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext
    const ctx = new AudioContextClass()
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()
    oscillator.type = 'sine'
    oscillator.frequency.value = 880
    gain.gain.setValueAtTime(0.15, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
    oscillator.connect(gain)
    gain.connect(ctx.destination)
    oscillator.start()
    oscillator.stop(ctx.currentTime + 0.3)
    oscillator.onended = () => ctx.close()
  } catch {
    // Ambiente sem suporte a Web Audio API (ex: alguns navegadores headless) — falha silenciosamente.
  }
}

export function useNotificacoes() {
  const { user } = useAuth()
  const qc = useQueryClient()
  const totalAnteriorRef = useRef(0)
  const habilitado = user?.perfil === 'admin' || user?.perfil === 'gestor'

  const { data } = useQuery({
    queryKey: ['notificacoes', 'nao-lidas'],
    queryFn: () => notificacoesApi.naoLidas().then(r => r.data),
    enabled: habilitado,
    refetchInterval: 20_000,
  })

  const total = data?.total ?? 0
  const notificacoes = data?.notificacoes ?? []

  useEffect(() => {
    if (total > totalAnteriorRef.current) {
      tocarBeep()
    }
    totalAnteriorRef.current = total
  }, [total])

  const marcarTodasLidas = async () => {
    await notificacoesApi.marcarTodasLidas()
    qc.setQueryData(['notificacoes', 'nao-lidas'], { total: 0, notificacoes: [] })
    totalAnteriorRef.current = 0
  }

  return { total, notificacoes, marcarTodasLidas }
}
```

- [ ] **Step 3: Verificar manualmente que o build do frontend continua funcionando**

Run: `npm run build`
Expected: build conclui sem erros (o hook ainda não é usado em nenhum componente, então não há regressão visual nesta task).

- [ ] **Step 4: Commit**

```bash
git add resources/js/api/notificacoes.js resources/js/hooks/useNotificacoes.js
git commit -m "feat: add notificacoes api client and useNotificacoes hook"
```

---

### Task 5: Sino de notificações no Header (badge, piscar, toast)

**Files:**
- Modify: `resources/js/components/layout/Header.jsx`

**Interfaces:**
- Consumes: `useNotificacoes()` (Task 4).
- Produces: UI do sino — nenhuma interface de código consumida por outras tasks.

- [ ] **Step 1: Editar `Header.jsx` para incluir o sino**

```jsx
import { useState } from 'react'
import { Menu, Bell } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import { useNotificacoes } from '../../hooks/useNotificacoes'

const perfilLabel = { admin: 'Administrador', gestor: 'Gestor', operador: 'Operador' }

export default function Header({ title, onMenuClick }) {
  const { user } = useAuth()
  const { total, notificacoes } = useNotificacoes()
  const [painelAberto, setPainelAberto] = useState(false)

  return (
    <header className="h-14 bg-white/80 backdrop-blur-sm border-b border-gray-200/80 flex items-center justify-between px-5 shrink-0 shadow-sm sticky top-0 z-20">
      <div className="flex items-center gap-3">
        <button
          onClick={onMenuClick}
          className="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
        >
          <Menu size={20} />
        </button>
        <h1 className="text-base font-semibold text-navy-900 tracking-tight">{title}</h1>
      </div>

      <div className="flex items-center gap-2">
        {(user?.perfil === 'admin' || user?.perfil === 'gestor') && (
          <div className="relative">
            <button
              onClick={() => setPainelAberto(v => !v)}
              className={`relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 ${total > 0 ? 'animate-pulse text-brand-600' : ''}`}
            >
              <Bell size={20} />
              {total > 0 && (
                <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] leading-none rounded-full w-4 h-4 flex items-center justify-center">
                  {total > 9 ? '9+' : total}
                </span>
              )}
            </button>
            {painelAberto && (
              <div className="absolute right-0 mt-2 w-72 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden z-30">
                <div className="px-4 py-2 text-xs font-semibold text-gray-500 border-b border-gray-100">Notificações</div>
                <div className="max-h-80 overflow-y-auto divide-y divide-gray-100">
                  {notificacoes.length === 0 && (
                    <div className="px-4 py-6 text-center text-sm text-gray-400">Nenhuma notificação nova</div>
                  )}
                  {notificacoes.map(n => (
                    <div key={n.id} className="px-4 py-3 text-sm">
                      <p className="font-medium text-gray-800">{n.data?.solicitante_nome ?? 'Nova solicitação'}</p>
                      <p className="text-gray-500 text-xs">{n.data?.detalhe}</p>
                    </div>
                  ))}
                </div>
                <div className="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                  Acesse Solicitações de Transporte para tratar e marcar como lido.
                </div>
              </div>
            )}
          </div>
        )}
        <div className="hidden sm:flex items-center gap-2 text-sm text-gray-600">
          <div className="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold">
            {user?.nome?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?'}
          </div>
          <span className="font-medium text-gray-700">{user?.nome?.split(' ')[0]}</span>
          <span className="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
            {perfilLabel[user?.perfil] ?? user?.perfil}
          </span>
        </div>
      </div>
    </header>
  )
}
```

Nota: abrir o painel do sino (`painelAberto`) NÃO chama `marcarTodasLidas` — conforme decidido, isso só acontece ao entrar em `/solicitacoes` (Task 6).

- [ ] **Step 2: Rodar o build do frontend**

Run: `npm run build`
Expected: build conclui sem erros.

- [ ] **Step 3: Testar manualmente no navegador**

Rodar `composer dev`, logar como gestor/admin, criar uma solicitação via outro usuário operador (ou via tinker/API), e verificar que o sino no Header pisca, mostra badge e, ao clicar, exibe a lista — sem tocar em `/solicitacoes` ainda.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/layout/Header.jsx
git commit -m "feat: add notification bell with badge and panel to header"
```

---

### Task 6: Marcar como lida ao entrar em Solicitações de Transporte

**Files:**
- Modify: `resources/js/pages/solicitacoes/SolicitacoesList.jsx`

**Interfaces:**
- Consumes: `useNotificacoes()` (Task 4), especificamente `marcarTodasLidas`.

- [ ] **Step 1: Editar `SolicitacoesList.jsx`**

Adicionar o import e o efeito no topo do componente (após os imports existentes, antes do `export default function SolicitacoesList()`):

```jsx
import { useEffect } from 'react'
import { useNotificacoes } from '../../hooks/useNotificacoes'
```

Dentro do componente `SolicitacoesList`, logo após `const [error, setError] = useState('')`:

```jsx
  const { marcarTodasLidas } = useNotificacoes()

  useEffect(() => {
    marcarTodasLidas()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
```

- [ ] **Step 2: Rodar o build do frontend**

Run: `npm run build`
Expected: build conclui sem erros.

- [ ] **Step 3: Testar manualmente no navegador**

Com uma solicitação pendente gerando badge no sino, navegar para `/solicitacoes` e confirmar que o badge some e a animação de piscar para.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/solicitacoes/SolicitacoesList.jsx
git commit -m "feat: mark notifications as read when entering solicitacoes screen"
```

---

### Task 7: Rodar a suíte completa e validar build final

**Files:** nenhum arquivo novo — task de verificação.

- [ ] **Step 1: Rodar toda a suíte de testes PHP**

Run: `php artisan test`
Expected: todos PASS, sem regressões nos testes existentes de Solicitacao, Notificacao, Checkin, Viagem, etc.

- [ ] **Step 2: Rodar o build final do frontend**

Run: `npm run build`
Expected: build conclui sem erros e sem warnings novos relacionados aos arquivos alterados.

- [ ] **Step 3: Revisão manual do fluxo completo**

Com `composer dev` rodando: logar como operador, criar uma solicitação; logar como gestor/admin em outra aba, confirmar que o sino pisca + badge aparece + beep toca; abrir o painel do sino (não deve zerar); navegar para `/solicitacoes` (deve zerar); verificar no log de e-mail (`storage/logs/laravel.log`, já que `MAIL_MAILER=log`) que o e-mail de `NovaSolicitacaoTransporte` foi registrado.

- [ ] **Step 4: Commit final (se houver ajustes desta verificação)**

```bash
git add -A
git commit -m "chore: final verification pass for solicitacao notification feature"
```

(Pular este commit se nenhum arquivo foi alterado durante a verificação.)
