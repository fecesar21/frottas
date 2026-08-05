# Design: Tela de Solicitação de Transporte

Data: 2026-07-31

## Contexto

O Health Drive/FleetCore hoje atende gestores, admins e motoristas via a SPA React existente (FleetApp). Não existe, porém, um canal para que colaboradores comuns solicitem transporte (transferência de paciente, busca de material, transporte de colaboradores, TFD, etc.) — essas necessidades chegam hoje por fora do sistema. O objetivo desta funcionalidade é criar uma tela dedicada onde qualquer colaborador possa abrir uma solicitação de transporte, acompanhar o status dela, e fazer com que o Gestor/Admin veja essas solicitações em tempo (quase) real no FleetApp para aceitá-las e despachar um motorista/veículo, conectando o pedido ao fluxo de viagens já existente.

Autenticação via LDAP é o objetivo final (autoprovisionamento de usuários do diretório corporativo), mas nesta primeira fase o login usa a mesma base local (Sanctum + tabela `usuarios`) que o resto do sistema, com a camada de autenticação desenhada para ser trocada depois sem reescrever a tela.

## Escopo

Dentro do escopo desta spec:
- Nova entidade `Solicitacao`, independente de `Viagem`, representando o pedido do colaborador antes de haver motorista/veículo.
- Novo SPA React ("tela a parte do sistema") para colaboradores solicitarem e acompanharem ("Minhas Solicitações").
- Novo menu no FleetApp existente para Gestor/Admin acompanhar solicitações em tempo (quase) real via polling e aceitá-las, criando a `Viagem` vinculada.

Fora do escopo (fases futuras):
- Integração LDAP real (host/bind/mapeamento de usuários).
- WebSockets/Reverb para tempo real instantâneo — v1 usa polling.
- Cancelamento avançado, notificações push/e-mail.

## Modelo de dados

Nova tabela `solicitacoes` (UUID PK via `HasUuids`, seguindo o padrão de `Viagem`/`Escala`):

| Campo | Tipo | Notas |
|---|---|---|
| `id` | uuid | PK |
| `usuario_id` | uuid FK `usuarios` | quem solicitou |
| `unidade_id` | uuid FK `unidades`, nullable | herdada do usuário; usada no escopo do Gestor |
| `motivo` | string | um de: `transferencia_paciente`, `buscar_medico_outra_cidade`, `material_outro_hospital`, `transporte_colaborador`, `buscar_material_fornecedor`, `tfd` — mesmo vocabulário já usado em `Viagem::motivo_viagem` |
| `origem_unidade_id` | uuid FK `unidades`, nullable | transferência de paciente / transporte de colaborador |
| `destino_unidade_id` | uuid FK `unidades`, nullable | idem |
| `numero_atendimento` | string(6), nullable | transferência de paciente |
| `cidade` | string, nullable | buscar médico em outra cidade |
| `hospital_destino` | string, nullable | levar material em outro hospital |
| `fornecedor_nome` | string, nullable | buscar material em fornecedor |
| `status` | string | `aberto`, `em_trajeto`, `finalizado`, `cancelado` |
| `viagem_id` | uuid FK `viagens`, nullable | preenchido quando o Gestor aceita |
| `observacoes` | text, nullable | |
| timestamps | | |

`App\Models\Solicitacao`: `belongsTo(Usuario)`, `belongsTo(Viagem)`, `belongsTo(Unidade, 'origem_unidade_id')`, `belongsTo(Unidade, 'destino_unidade_id')`, `belongsTo(Unidade)`.

Fluxo de status: criada como `aberto` → Gestor/Admin aceita (escolhe motorista + veículo) → backend cria `Viagem` com os dados copiados da solicitação, grava `viagem_id`, muda status para `em_trajeto` → quando a `Viagem` recebe `chegada_at`, a Solicitação passa a `finalizado` (via observer/accessor que reflete o status da viagem vinculada).

## API

Novo `App\Http\Controllers\Api\SolicitacaoController` + `StoreSolicitacaoRequest`/`UpdateSolicitacaoRequest`, seguindo o padrão de `ViagemController`:

- `POST /api/solicitacoes` — cria (qualquer usuário autenticado)
- `GET /api/solicitacoes` — lista; colaborador comum vê só as suas (`usuario_id` = usuário logado); Gestor/Admin vê conforme `EscopoUnidade` (Gestor: sua unidade; Admin: todas)
- `GET /api/solicitacoes/{id}` — detalhe
- `PATCH /api/solicitacoes/{id}/aceitar` — Gestor/Admin only; body `{motorista_id, veiculo_id}`; cria a `Viagem` vinculada
- `PATCH /api/solicitacoes/{id}/cancelar` — dono da solicitação ou Gestor/Admin

Reaproveita `GET /api/unidades` já existente para popular os selects de Origem/Destino.

## Frontend — novo SPA de Solicitação de Transporte

Novo entrypoint Vite em `resources/solicitacao-js/` (build para `public/solicitacao/`), servido por uma view Blade mínima nova em `/solicitar`. Estrutura enxuta reaproveitando padrões do FleetApp (axios client, AuthContext adaptado para este app):

- **Login**: usuário/senha contra `POST /api/auth/login` (mesmo endpoint do resto do sistema). Camada de auth isolada num único módulo para facilitar troca futura por LDAP.
- **Formulário de solicitação** (tela inicial após login): seleção de Motivo (radio/checkbox conforme o pedido original) revela os campos condicionais correspondentes (origem/destino, número de atendimento, cidade, hospital, fornecedor). Botão Enviar → `POST /api/solicitacoes`.
- **Menu lateral "Minhas Solicitações"**: lista via `GET /api/solicitacoes`, exibindo Data, Motivo, Status, Nome do Motorista (`viagem.motorista.nome`, quando houver).

## Frontend — FleetApp existente

Novo item de menu "Solicitações de Transporte" (visível a `admin` e `gestor`), nova página em `resources/js/pages/solicitacoes/`:
- Tabela com polling a cada 10s (`GET /api/solicitacoes`) mostrando as solicitações em aberto/andamento.
- Ação "Aceitar" abre modal para escolher motorista + veículo disponíveis → `PATCH /api/solicitacoes/{id}/aceitar`.

## Testes

Seguir o padrão de testes de Feature já usado para `Viagem` (`tests/Feature`):
- Criação de solicitação com validação por motivo (campos obrigatórios variam conforme `motivo`).
- Escopo de listagem (colaborador só vê as próprias; Gestor vê da unidade; Admin vê todas).
- Aceitar solicitação cria `Viagem` vinculada e atualiza status.
- Sincronização de status quando a `Viagem` recebe `chegada_at`.

## Verificação end-to-end

- `php artisan test` cobrindo os cenários acima.
- Rodar `composer dev`, logar no novo SPA (`/solicitar`) com um usuário de teste, criar uma solicitação de cada tipo de motivo e conferir os campos condicionais.
- No FleetApp, logar como gestor/admin, conferir que a solicitação aparece na nova página em até 10s, aceitar vinculando motorista/veículo, e verificar que uma `Viagem` foi criada e que o status da solicitação evolui corretamente.
