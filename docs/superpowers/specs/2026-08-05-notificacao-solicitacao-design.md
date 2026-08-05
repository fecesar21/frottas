# Notificação de nova solicitação de transporte para gestor/admin

## Contexto

Hoje, quando um operador cria uma solicitação de transporte (`Solicitacao`), nenhum gestor/admin é avisado ativamente — precisam entrar na tela de Solicitações para descobrir. O objetivo é notificar o gestor/admin assim que uma solicitação chegar, por três canais: notificação in-app (sino piscando + bipe), pop-up/toast na tela, e e-mail.

## Escopo

- Disparo de notificação ao criar uma `Solicitacao` (`SolicitacaoService::store()`).
- Destinatários: usuários com perfil `admin` (globais, todas as unidades) + usuários com perfil `gestor` vinculados à mesma `unidade_id` da solicitação.
- Canais: `database` (Laravel Notifications, consultado via polling) e `mail` (Mailable, usando o mailer configurado no `.env` — hoje `log`, podendo virar SMTP real depois sem mudança de código).
- Frontend: sino com badge de contagem de não lidas, animação de piscar + beep sintetizado enquanto houver não lidas, e toast ao detectar novas notificações via polling.
- Marcação como lida: ocorre quando o usuário navega para a tela `/solicitacoes` (não ao simplesmente abrir o dropdown do sino).

Fora de escopo: WebSockets/broadcast em tempo real, configuração de credenciais SMTP reais, notificações para eventos além da criação de solicitação (aceite, cancelamento, etc — podem vir depois se necessário).

## Backend

### Notification

- `App\Notifications\NovaSolicitacaoTransporte implements ShouldQueue`, canais `['database', 'mail']`.
- `toDatabase()`: payload com `solicitacao_id`, `origem`, `destino`, `motivo`, `solicitante_nome`, `unidade_id`.
- `toMail()`: Mailable simples (`MailMessage`) com assunto "Nova solicitação de transporte", corpo com origem/destino/motivo/solicitante, e um botão/link para `FRONTEND_URL/solicitacoes`.

### Model `Usuario`

- Adicionar trait `Illuminate\Notifications\Notifiable`.

### Migration

- `php artisan notifications:table` (tabela padrão `notifications`: `id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, timestamps).

### `SolicitacaoService::store()`

Após `Solicitacao::create($data)`, buscar destinatários:

```php
$destinatarios = Usuario::where('perfil', 'admin')
    ->orWhere(fn ($q) => $q->where('perfil', 'gestor')->where('unidade_id', $solicitacao->unidade_id))
    ->get();

Notification::send($destinatarios, new NovaSolicitacaoTransporte($solicitacao));
```

### Endpoints novos (`routes/api.php`, dentro do grupo `auth:sanctum`)

- `GET /api/notificacoes/nao-lidas` → retorna `{ total: int, notificacoes: [...] }` das notificações não lidas do usuário autenticado (limit 20, mais recentes primeiro).
- `POST /api/notificacoes/marcar-lidas` → marca todas as notificações não lidas do usuário autenticado como lidas (`markAsRead()`), retorna 204.

Novo `NotificacaoController` simples, sem Service dedicado (lógica trivial, delega ao `Notifiable::notifications()`/`unreadNotifications()`).

## Frontend

### Hook `useNotificacoes`

- Ativo apenas quando `user.perfil` é `admin` ou `gestor`.
- Polling a cada 20s em `GET /api/notificacoes/nao-lidas`.
- Mantém estado `{ total, notificacoes, temNovas }`. Quando a contagem aumenta em relação à leitura anterior, dispara toast com a notificação mais recente e o beep.
- Beep: gerado via `AudioContext` (oscilador curto), sem depender de arquivo de áudio.

### UI

- Sino no `Layout`/`Sidebar` (área do topo, perto do avatar do usuário) com badge numérico.
- Enquanto `total > 0`: classe CSS de piscar (`animate-pulse` ou similar) no ícone do sino.
- Toast: componente leve reutilizando o padrão de UI já existente no projeto (verificar `Badge`/toasts existentes); some sozinho após alguns segundos.
- Ao montar a rota `/solicitacoes` (ou no componente `SolicitacoesList`), chamar `POST /api/notificacoes/marcar-lidas` e resetar o estado do hook.

## Testes

- Feature test: criar uma `Solicitacao` com `Notification::fake()` e asserir que `NovaSolicitacaoTransporte` foi enviada para os admins + gestor da unidade correta, e não enviada para gestor de outra unidade nem para operadores.
- Feature test para `GET /api/notificacoes/nao-lidas` e `POST /api/notificacoes/marcar-lidas` (contagem correta, marca como lida, isolamento por usuário).

## Erros / edge cases

- Sem gestor cadastrado na unidade: admins globais ainda recebem normalmente (união, não interseção).
- Fila não rodando: e-mail fica pendente na tabela `jobs` até o worker processar (comportamento padrão do Laravel `composer dev`); notificação `database` não depende da fila para leitura, mas como o canal também roda via `ShouldQueue`, ambas ficam pendentes juntas — aceitável pois o projeto já roda `queue:work` continuamente.
- `MAIL_MAILER=log`: e-mails continuam sendo gravados em log até as credenciais SMTP reais serem configuradas no `.env` (fora de escopo).
