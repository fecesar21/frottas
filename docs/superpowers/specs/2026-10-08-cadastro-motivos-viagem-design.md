# Cadastro de Motivos de Viagem — Design

Data: 2026-10-08 · Status: aprovado em conversa, aguardando revisão da spec

## Objetivo

Permitir que o admin cadastre, edite e inative motivos de viagem pela tela
(Configurações → Motivos de Viagem), definindo para cada motivo o tipo de veículo
(administrativo, ambulância ou ambos) e se fica disponível no app de solicitações.
Novos motivos deixam de exigir alteração de código e deploy.

## Situação atual

Os motivos estão fixos no código em vários pontos:

- `app/Models/Viagem.php` — `ROTULOS_MOTIVO`
- `app/Models/Veiculo.php` — `MOTIVOS_SOMENTE_AMBULANCIA` / `MOTIVOS_SOMENTE_ADMINISTRATIVO` + `permiteMotivoViagem()`
- `app/Http/Requests/Viagem/StoreViagemRequest.php` e `UpdateViagemRequest.php` — regra `in:`
- `app/Http/Requests/Solicitacao/StoreSolicitacaoRequest.php` — regra `in:` (6 motivos)
- `resources/views/relatorios/pdf/viagens.blade.php` — mapa de rótulos
- `resources/js/utils/solicitacao.js` — `MOTIVOS_SOLICITACAO`
- `resources/js/pages/viagens/ViagemForm.jsx` — listas por tipo de veículo
- `resources/solicitacao-js/pages/NovaSolicitacao.jsx` — lista de 6 motivos
- `config/solicitacao.php` — roteamento de notificações por motivo
- Notificações (`NovaSolicitacaoTransporte`, `NovaSolicitacaoDisponivel`) exibem o código cru

## Abordagem

Tabela `motivos_viagem` cujo `codigo` (texto) continua sendo o valor gravado em
`viagens.motivo_viagem` e `solicitacoes.motivo`. Nenhum dado existente é migrado; as
regras especiais seguem comparando códigos (`'transferencia_paciente'` etc.).

Descartadas: FK `motivo_id` (migração pesada de dados em produção sem ganho prático) e
lista em JSON de configuração (validação fraca, foge do padrão do projeto).

## Modelo de dados

Tabela `motivos_viagem` (model `MotivoViagem`, `HasUuids`):

| Coluna | Tipo | Regra |
|---|---|---|
| `id` | uuid PK | |
| `codigo` | string, unique | Gerado do nome na criação (`Str::slug(nome, '_')`, sufixo numérico se colidir); imutável |
| `nome` | string | Editável, obrigatório, único |
| `tipo_veiculo` | enum `administrativo`/`ambulancia`/`ambos` | Travado nos motivos de sistema |
| `disponivel_solicitacao` | boolean, default false | |
| `ativo` | boolean, default true | |
| `sistema` | boolean, default false | Não editável pela API |
| timestamps | | |

Seed na própria migration (todos `sistema = true`, `ativo = true`):

| codigo | nome | tipo_veiculo | solicitação |
|---|---|---|---|
| transferencia_paciente | Transferência de Paciente | ambulancia | sim |
| tfd | TFD | ambulancia | sim |
| buscar_medico | Buscar Médico em Outra Cidade | administrativo | sim |
| material_outro_hospital | Levar Material em Outro Hospital | administrativo | sim |
| transporte_colaborador | Transporte de Colaborador(es) | administrativo | sim |
| buscar_material_fornecedor | Buscar Materiais em Fornecedor | administrativo | sim |
| alimentacao | Alimentação (Levar/Buscar) | administrativo | não |
| servicos_administrativos | Serviços Administrativos Diversos | administrativo | não |

## Regras de negócio

- **Compatibilidade com veículo:** `MotivoViagem::permiteVeiculo(Veiculo $v)` — `ambos` aceita
  qualquer; senão compara com `Veiculo::ehAmbulancia()`. Substitui `Veiculo::permiteMotivoViagem()`
  e as constantes `MOTIVOS_SOMENTE_*`.
- **Inativo:** some dos formulários e é recusado na validação de novas viagens/solicitações;
  continua resolvendo o nome em relatórios e listagens. Edição de viagem existente que mantém o
  mesmo motivo inativo continua válida.
- **Sistema:** pode renomear e inativar; não pode excluir nem mudar `tipo_veiculo`.
- **Exclusão:** só motivo não-sistema e sem nenhuma viagem/solicitação usando o código; caso
  contrário responde 422 com mensagem orientando a inativar.
- **Rótulo:** `MotivoViagem::rotulo(?string $codigo)` com cache em memória da requisição;
  vazio → "Não informado"; código desconhecido → fallback atual (`Str::title`). Substitui
  `Viagem::rotuloMotivo()` (mantido como delegação para não quebrar chamadores) e
  `ROTULOS_MOTIVO`.

## API

Controller `App\Http\Controllers\Api\MotivoViagemController`:

- `GET /api/motivos-viagem` — `auth:sanctum`. Padrão: só ativos. `?todos=1` (admin): inclui
  inativos. `?solicitacao=1`: só `disponivel_solicitacao`. Ordenado por nome.
  Resource: `id, codigo, nome, tipo_veiculo, disponivel_solicitacao, ativo, sistema, em_uso`
  (`em_uso` só no modo `todos`).
- `POST /api/motivos-viagem` — `admin`. Campos: `nome`, `tipo_veiculo`, `disponivel_solicitacao`, `ativo`.
- `PUT /api/motivos-viagem/{id}` — `admin`. Mesmos campos; `tipo_veiculo` ignorado/recusado (422) em sistema.
- `DELETE /api/motivos-viagem/{id}` — `admin`. Regras de exclusão acima.

## Validação

- `StoreViagemRequest` / `UpdateViagemRequest`: `motivo_viagem` deve existir em
  `motivos_viagem.codigo` com `ativo = true` (no update, aceita também o motivo atual da
  viagem) e ser compatível com o veículo (mensagens atuais mantidas).
- `StoreSolicitacaoRequest`: motivo existente, ativo e `disponivel_solicitacao = true`.
  As regras `required_if` específicas (origem/destino, atendimento, cidade etc.) não mudam.

## Frontend — painel (`resources/js`)

- `api/motivosViagem.js` (list/create/update/remove) e hook `useMotivosViagem()` (React Query,
  chave `['motivos-viagem']`).
- **Configurações:** novo cartão "Motivos de Viagem" no `ConfiguracoesHub`, rota
  `/configuracoes/motivos-viagem` (`AdminRoute`). Página `pages/configuracoes/MotivosViagem.jsx`
  no padrão de Localidades: tabela (Nome, Tipo de veículo, Solicitações, Status, ações),
  filtro "mostrar inativos", modal de criação/edição; selo "Sistema" e select de tipo
  desabilitado nos de sistema; botão Excluir só quando `!sistema && !em_uso`.
- **ViagemForm:** `motivosDoVeiculo(motivos, veiculo)` filtra pela API (`tipo_veiculo`) em vez
  das constantes; sem veículo mostra todos os ativos.
- **Rótulos:** `MOTIVOS_SOLICITACAO`/`rotuloMotivo` passam a usar os dados do hook; telas que
  exibem motivo (RelatorioViagens, SolicitacoesList) usam o nome vindo da API. Onde for mais
  simples, os Resources de Viagem/Solicitação passam a incluir `motivo_nome`.

## Frontend — solicitações (`resources/solicitacao-js`)

- `NovaSolicitacao` carrega `GET /api/motivos-viagem?solicitacao=1`.
- Campos específicos continuam presos aos códigos de sistema; motivo não-sistema mostra apenas
  o campo de observações existente.
- `MinhasSolicitacoes` exibe `motivo_nome`.

## Relatórios e notificações

- PDF de viagens e `RelatorioController::viagensPorMotivo` / `ResumoOperacionalService` usam
  `MotivoViagem::rotulo()`; remove o mapa fixo do Blade.
- `NovaSolicitacaoTransporte`, `NovaSolicitacaoDisponivel` e `NovaViagemDesignada` exibem o nome.

## Roteamento de notificações (`RoteamentoSolicitacaoService::regra`)

1. Se o código tem entrada em `config('solicitacao.roteamento_motoristas')` → comportamento atual.
2. Senão, se o motivo é de sistema (ex.: TFD) → só admin/gestor (atual).
3. Senão (motivo cadastrado pela tela) → motoristas com check-in ativo em veículo compatível com
   `tipo_veiculo`, fora de manutenção, sem restrição de unidade; admin/gestor seguem notificados
   como hoje.

## Testes

- Feature `MotivoViagemApiTest`: listagem (ativos, `todos`, `solicitacao`), CRUD só admin (403
  para operador), geração de código único, sistema não exclui nem muda tipo, exclusão bloqueada
  quando em uso, permitida quando não usado.
- `ViagemApiTest`: motivo inativo recusado; motivo `ambos` aceito nos dois tipos; testes atuais
  de compatibilidade continuam passando.
- Solicitação: motivo indisponível em solicitações recusado; motivo novo aceito.
- Roteamento: motivo novo administrativo notifica motorista com check-in em Strada e não o de
  ambulância; TFD continua só gestão.
- Vitest: `motivosDoVeiculo` com dados da API; página de motivos (render, selo sistema, botão excluir).

## Fora de escopo

- Configurar pela tela campos extras por motivo ou o roteamento por modelo/unidade
  (permanece em `config/solicitacao.php` para os de sistema).
- Reordenação manual (ordem alfabética).
