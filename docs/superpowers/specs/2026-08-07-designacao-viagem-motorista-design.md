# Aceite/recusa de viagem designada pelo gestor (motorista)

## Contexto

Hoje, quando o gestor clica em "Aceitar" numa `Solicitacao` e escolhe motorista/veículo (`SolicitacaoController::aceitar` → `SolicitacaoService::aceitar()`), o sistema **já cria a `Viagem` imediatamente** (`km_saida = 0`, `status = em_andamento`), sem qualquer confirmação do motorista. Se o motorista já tiver uma viagem `em_andamento`, a solicitação fica em `aguardando_finalizacao_trajeto` e, ao concluir a viagem atual, `SolicitacaoService::processarPendentePara()` cria a próxima viagem sozinha, reaproveitando o `km_chegada` da anterior como `km_saida` — também sem input do motorista.

O motorista nunca é notificado dessas designações; só percebe ao abrir a lista de viagens.

Esta spec muda esse fluxo: o gestor passa a **designar** (não mais efetivar), e é o **motorista** quem efetiva a viagem — aceitando (e informando o KM de saída real) ou recusando (com motivo, visível só ao gestor). Reaproveita o padrão de pop-up de notificação já existente para o gestor (`useNotificacoes` + polling 20s + `Notification`/`database` channel), agora também para o motorista.

## Escopo

- Notificar o motorista designado assim que o gestor aceitar uma solicitação em seu nome.
- Pop-up "Nova Viagem Designada pelo Gestor" no app do motorista, sem fechamento automático, com botões ACEITAR e RECUSAR.
- ACEITAR:
  - Sem viagem `em_andamento`: abre modal para informar `km_saida` e efetiva a criação da `Viagem` (`status = em_andamento`).
  - Com viagem `em_andamento`: entra na fila (FIFO, múltiplas designações permitidas); mostra indicador "próxima viagem na fila". Ao finalizar a viagem atual, a próxima da fila dispara automaticamente o modal de KM de saída para o motorista (sem criar a viagem sozinha).
- RECUSAR: modal com campo de motivo obrigatório; a solicitação volta para o gestor como `recusada` com o motivo visível **apenas para o gestor** (nunca para o solicitante original). O gestor pode então reatribuir outro motorista/veículo a partir desse estado.
- Reaproveita o componente visual e o hook de polling já usados no pop-up do gestor (modal centralizado via transform, padrão Tailwind, `lucide-react`).

Fora de escopo: WebSockets/broadcast em tempo real (mantém polling), alterar o fluxo de criação da `Solicitacao` pelo solicitante, e-mail para o motorista (só canal `database`, igual ao pop-up do gestor).

## Máquina de estados de `Solicitacao`

Estados atuais: `aberto`, `em_trajeto`, `aguardando_finalizacao_trajeto`, `cancelado`.

Novos/alterados:

| Status | Quando entra | Quem move para o próximo estado |
|---|---|---|
| `aberto` | Criação da solicitação | Gestor designa |
| `pendente_motorista` *(novo)* | Gestor chama `aceitar()` (designa motorista/veículo) — **não cria mais a Viagem aqui** | Motorista aceita ou recusa |
| `recusada` *(novo)* | Motorista recusa, com `motivo_recusa` preenchido | Gestor redesigna (chama `aceitar()` de novo, tratado como `aberto`) |
| `aguardando_finalizacao_trajeto` | Motorista aceita mas já tem `Viagem em_andamento` | Ao concluir a viagem atual, notifica o motorista para informar `km_saida` da próxima; ele confirma via mesmo endpoint de aceite |
| `em_trajeto` | Motorista aceita (sem viagem ativa) e informa `km_saida`, OU dequeue de `aguardando_finalizacao_trajeto` após informar `km_saida` | Conclusão normal da viagem (fluxo já existente) |
| `cancelado` | Cancelamento (fluxo já existente) | — |

`motorista_pendente_id`/`veiculo_pendente_id` (campos já existentes) passam a ser preenchidos assim que o gestor designa (`pendente_motorista`) e permanecem até a `Viagem` ser efetivamente criada.

## Backend

### Migration

- `solicitacoes`: novo campo `motivo_recusa` (`text`, nullable).

### `SolicitacaoService`

- `aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId)` (chamado pelo **gestor**): passa a apenas `update(['status' => 'pendente_motorista', 'motorista_pendente_id' => $motoristaId, 'veiculo_pendente_id' => $veiculoId])` e notificar o `Usuario` do motorista (`Motorista::usuario()`) via `NovaViagemDesignada`. Não cria mais `Viagem`. Também deve funcionar quando `$solicitacao->status === 'recusada'` (redesignação), limpando `motivo_recusa`.
- Novo `motoristaAceitar(Solicitacao $solicitacao, string $motoristaId, ?int $kmSaida)`:
  - Autoriza: `$solicitacao->motorista_pendente_id === $motoristaId` e `status` em `['pendente_motorista', 'aguardando_finalizacao_trajeto']`.
  - Se motorista tem `Viagem` `em_andamento` **e** `$kmSaida === null`: apenas `update(['status' => 'aguardando_finalizacao_trajeto'])` (entra/permanece na fila; não cria viagem).
  - Caso contrário (sem viagem ativa, ou dequeue informando `kmSaida`): exige `$kmSaida` (valida presença — `422` se ausente), cria a `Viagem` com o `km_saida` informado (reaproveita `efetivarAceite`, mas usando o KM informado pelo motorista em vez de `0`/KM anterior automático), `status = em_trajeto`.
- Novo `motoristaRecusar(Solicitacao $solicitacao, string $motoristaId, string $motivo)`: autoriza igual acima, `update(['status' => 'recusada', 'motivo_recusa' => $motivo, 'motorista_pendente_id' => null, 'veiculo_pendente_id' => null])`, notifica gestores responsáveis (reaproveita destinatários calculados como em `store()`) via nova notificação `SolicitacaoRecusadaPeloMotorista`. Não notifica o solicitante.
- `processarPendentePara(string $motoristaId)`: ao concluir uma viagem, em vez de efetivar sozinha, apenas localiza a solicitação `aguardando_finalizacao_trajeto` mais antiga daquele motorista (`oldest()`, mantém FIFO) e envia notificação `NovaViagemDesignada` (mesmo tipo, reaproveitado) avisando que é hora de informar o KM de saída. A `Viagem` só nasce quando o motorista chamar `motoristaAceitar` com `kmSaida`.

### Notifications

- `App\Notifications\NovaViagemDesignada implements ShouldQueue`, canal `['database']`. Payload: `solicitacao_id`, origem/destino, `motivo`, flag `fila` (bool — indica se é uma designação nova ou um aviso de "sua vez chegou" para item já na fila).
- `App\Notifications\SolicitacaoRecusadaPeloMotorista implements ShouldQueue`, canal `['database']`. Payload: `solicitacao_id`, `motorista_nome`, `motivo_recusa`.

### Endpoints (`routes/api.php`, grupo `auth:sanctum`)

- `PATCH /api/solicitacoes/{solicitacao}/motorista-aceitar` — body opcional `{ km_saida?: int }`. Só o motorista designado pode chamar (verificar `usuario()->motorista_id` contra `motorista_pendente_id`).
- `PATCH /api/solicitacoes/{solicitacao}/motorista-recusar` — body `{ motivo: string }` (obrigatório).

### `SolicitacaoController`

- Novas actions `motoristaAceitar` e `motoristaRecusar`, delegando ao service; retornam a `Solicitacao` atualizada (`fresh()`).
- Endpoint de listagem para o motorista buscar suas designações pendentes (reaproveitar `GET /api/notificacoes/nao-lidas` já filtra por `Usuario` autenticado — nenhuma rota nova necessária além das duas acima, o hook de polling do motorista consulta o mesmo endpoint de notificações).

## Frontend

### Hook

- Reaproveitar `useNotificacoes` (já filtra por usuário autenticado via `notifications` table) — funciona tal como está para o motorista, sem necessidade de hook separado, desde que o payload da notificação traga o suficiente para renderizar o pop-up (`solicitacao_id`, origem/destino, motivo, `fila`).
- Diferença de comportamento por tipo de notificação: `Header.jsx`/tela do operador precisa distinguir `NovaViagemDesignada` (motorista) de `NovaSolicitacaoTransporte` (gestor) para renderizar o componente certo — usar o campo `type` já presente nas notificações do Laravel.

### Componente `NovaViagemDesignadaPopup.jsx`

- Clonado de `NovaSolicitacaoPopup.jsx`: mesmo modal centralizado (`fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2`), **sem** `AUTO_FECHAR_MODAL_MS`.
- Texto: "Nova Viagem Designada pelo Gestor" + origem/destino/motivo.
- Botões ACEITAR / RECUSAR:
  - ACEITAR → chama `GET` do status atual do motorista (já disponível via query de viagens/checkin ativo) para saber se há viagem `em_andamento`:
    - Sem viagem ativa: abre `KmSaidaModal` (novo componente pequeno, input numérico obrigatório) → ao confirmar, `PATCH .../motorista-aceitar` com `{ km_saida }`.
    - Com viagem ativa: `PATCH .../motorista-aceitar` sem body → fecha pop-up, mostra toast "Viagem adicionada à fila".
  - RECUSAR → abre `MotivoRecusaModal` (textarea obrigatório) → `PATCH .../motorista-recusar` com `{ motivo }`.

### Indicador de fila

- Em `ViagemDetalhe.jsx`/`ViagensList.jsx` (tela do motorista com viagem `em_andamento`), badge fixo "Próxima viagem aguardando" quando existir solicitação `aguardando_finalizacao_trajeto` para o motorista autenticado (consulta leve, reaproveitando polling existente de 30s da tela).
- Quando a notificação `NovaViagemDesignada` chega com `fila = true` (viagem anterior concluída, é a vez desta), o pop-up abre automaticamente pedindo o KM de saída, pulando a etapa ACEITAR/RECUSAR (já foi aceita antes).

### Tela do gestor (`SolicitacoesList.jsx`)

- Exibir badge "Recusada pelo motorista" com o `motivo_recusa` quando `status === 'recusada'`.
- Reabrir o modal de aceite (mesmo formulário existente) a partir do estado `recusada`, permitindo escolher outro motorista/veículo.

## Testes

- Feature: gestor designa → `Solicitacao` fica `pendente_motorista`, nenhuma `Viagem` criada, motorista recebe notificação `NovaViagemDesignada`.
- Feature: motorista sem viagem ativa aceita com `km_saida` → cria `Viagem` `em_andamento` com o KM informado, solicitação `em_trajeto`.
- Feature: motorista com viagem ativa aceita sem `km_saida` → solicitação `aguardando_finalizacao_trajeto`, nenhuma `Viagem` nova criada.
- Feature: conclusão da viagem atual dispara notificação para a solicitação enfileirada (sem criar viagem sozinha); motorista então chama `motorista-aceitar` com `km_saida` e a viagem é criada.
- Feature: motorista recusa → `Solicitacao` fica `recusada` com `motivo_recusa`; gestor recebe notificação; solicitante não recebe nada; gestor consegue redesignar (`aceitar()` novamente) com outro motorista.
- Feature: motorista tenta aceitar/recusar solicitação que não é dele → `403`.
- Feature: motorista aceita sem viagem ativa e sem informar `km_saida` → `422`.
- Feature FIFO: duas solicitações designadas ao mesmo motorista enquanto ele está em viagem → ambas aceitas entram na fila; ao concluir a viagem atual, a **mais antiga** é a que dispara a notificação de "informe o KM".

## Erros / edge cases

- Motorista recusa uma solicitação já enfileirada (`aguardando_finalizacao_trajeto`, ainda não é a vez dela): mesmo fluxo de recusa se aplica — remove da fila, gestor redesigna.
- Gestor redesigna uma solicitação `recusada` para o **mesmo** motorista que recusou: permitido (não há bloqueio de negócio pedido), volta a `pendente_motorista`.
- Duas notificações concorrentes de fila para o mesmo motorista (dois `motorista-aceitar` quase simultâneos ao concluir viagem): `motoristaAceitar` deve rodar em transação (`DB::transaction`) e revalidar que ainda não existe outra `Viagem em_andamento` do motorista antes de criar, evitando duas viagens simultâneas.
