# Cadastro de Localidades para Origem/Destino de Solicitações

## Contexto

Na tela de Nova Solicitação (`resources/solicitacao-js/pages/NovaSolicitacao.jsx`),
os motivos `transferencia_paciente` e `transporte_colaborador` exigem Origem e
Destino (`origem_unidade_id` / `destino_unidade_id`). Hoje as únicas opções
disponíveis são as `unidades` cadastradas (hospitais/filiais da própria
empresa) — retornadas por `GET /unidades` (`UnidadeController@index`). Isso
impede referenciar locais fora da rede de unidades: hospitais parceiros,
clínicas de destino de transferência, endereços de empresas parceiras que
recebem colaboradores, etc. O operador hoje precisa digitar esses casos em
campo livre (`hospital_destino`) só quando o motivo é
`material_outro_hospital`, e para `transferencia_paciente`/
`transporte_colaborador` fica restrito à lista de unidades.

## Escopo

- Nova entidade **Localidade**: nome, endereço, latitude/longitude, telefone,
  e-mail, ativo/inativo. CRUD via nova sub-tela **Cadastro de Localidades**
  dentro do hub de Configurações do admin.
- Lista global — toda unidade usa o mesmo cadastro de Localidades (sem
  vínculo a uma unidade específica).
- Nos selects de Origem e Destino de `transferencia_paciente` e
  `transporte_colaborador`, as opções passam a ser **Unidades ativas +
  Localidades ativas**, combinadas numa lista única.
- `solicitacoes.origem_unidade_id`/`destino_unidade_id` (FK única para
  `unidades`) são substituídos por colunas polimórficas
  `origem_tipo`/`origem_id` e `destino_tipo`/`destino_id`, com migração dos
  dados existentes (`origem_tipo = 'unidade'` para todo registro atual).

Fora de escopo: localidades por unidade (lista fica global); múltiplos
contatos por localidade (só um telefone + um e-mail); geocodificação
automática de endereço → lat/long (os três campos são preenchidos manualmente
pelo admin); alterar o motivo `material_outro_hospital` (continua usando o
campo livre `hospital_destino`).

## Backend

### Migration — nova tabela `localidades`

```php
Schema::create('localidades', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nome', 150);
    $table->string('endereco', 255)->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('telefone', 20)->nullable();
    $table->string('email')->nullable();
    $table->boolean('ativo')->default(true);
    $table->timestamps();
});
```

### Model `Localidade`

`app/Models/Localidade.php` — `HasUuids`, `$fillable = ['nome', 'endereco',
'latitude', 'longitude', 'telefone', 'email', 'ativo']`. Sem relações
obrigatórias no momento (nada referencia `localidade_id` diretamente; a
ligação com `solicitacoes` é via as colunas polimórficas abaixo).

### Controller e rotas — `LocalidadeController`

Segue o padrão de `UnidadeController`: `index`, `store`, `show`, `update`,
`destroy` (soft — `destroy` seta `ativo = false`, nunca apaga a linha, para
preservar o histórico de solicitações que referenciam a localidade).
Autorização: `store`/`update`/`destroy` exigem `perfil === 'admin'`
(`abort_unless`, mesmo padrão de `UnidadeController`); `index`/`show`
acessíveis a qualquer usuário autenticado.

Rotas em `routes/api.php`, no mesmo grupo autenticado onde vive `unidades`:

```php
Route::get('localidades', [LocalidadeController::class, 'index']);
Route::post('localidades', [LocalidadeController::class, 'store']);
Route::get('localidades/{localidade}', [LocalidadeController::class, 'show']);
Route::put('localidades/{localidade}', [LocalidadeController::class, 'update']);
Route::delete('localidades/{localidade}', [LocalidadeController::class, 'destroy']);
```

Validação (`Store`/`UpdateLocalidadeRequest`): `nome` obrigatório;
`endereco`, `telefone`, `email` (formato e-mail), `latitude`
(`numeric|between:-90,90`), `longitude` (`numeric|between:-180,180`) opcionais.

### Endpoint agregador — `GET /pontos-viagem`

Novo endpoint simples (`PontoViagemController@index` ou método dedicado) que
retorna unidades ativas + localidades ativas já normalizadas:

```json
[
  { "tipo": "unidade", "id": "...", "nome": "Hospital Central" },
  { "tipo": "localidade", "id": "...", "nome": "Clínica Parceira ABC" }
]
```

Ordenado por `nome`. Consumido só pelo formulário de Nova Solicitação — evita
duas chamadas (`unidades` + `localidades`) e lógica de merge duplicada no
frontend.

### Alteração em `solicitacoes`

Nova migration:

1. Adiciona colunas `origem_tipo` (`string`, nullable), `destino_tipo`
   (`string`, nullable) — valores `'unidade'` ou `'localidade'` — e renomeia
   `origem_unidade_id` → `origem_id`, `destino_unidade_id` → `destino_id`
   (mantendo tipo `uuid` nullable, **sem** FK de banco — já que agora podem
   apontar para duas tabelas diferentes; integridade referencial passa a ser
   responsabilidade da aplicação/validação, não do schema).
2. `UPDATE solicitacoes SET origem_tipo = 'unidade' WHERE origem_id IS NOT
   NULL` (idem para `destino_tipo`) — preenche os registros existentes.

`App\Models\Solicitacao`: adicionar accessor/relação auxiliar (`origem()`,
`destino()`) que resolve `Unidade::find` ou `Localidade::find` conforme
`origem_tipo`/`destino_tipo`, usado por `SolicitacaoResource` para expor
`origem`/`destino` já resolvidos (nome, endereço) nas respostas da API.

`StoreSolicitacaoRequest`: trocar `origem_unidade_id`/`destino_unidade_id`
por `origem_tipo` (`required_if:motivo,...|in:unidade,localidade`),
`origem_id` (`required_if:...|uuid`, validado via regra customizada que
checa existência na tabela certa conforme o tipo — `exists` não suporta
tabela dinâmica), e o mesmo para destino.

## Frontend admin (`resources/js`)

- `ConfiguracoesHub.jsx`: adicionar card "Cadastro de Localidades" ao array
  `secoes`.
- Nova página `pages/configuracoes/LocalidadesConfig.jsx`: lista (nome,
  endereço, telefone, ativo/inativo) + formulário de criar/editar
  (nome, endereço, latitude, longitude, telefone, email, ativo), usando
  `@tanstack/react-query`, seguindo o padrão de `ConfiguracoesLdap.jsx`.
- API client `resources/js/api/localidades.js` espelhando `api/unidades.js`.
- Rota `/configuracoes/localidades` em `FleetApp.jsx`, dentro de
  `<AdminRoute>`.

## Frontend de solicitação (`resources/solicitacao-js`)

- `NovaSolicitacao.jsx`: trocar a chamada `unidadesApi.listar()` por uma
  chamada ao novo endpoint `GET /pontos-viagem`, populando os selects de
  Origem/Destino com a lista combinada `{ tipo, id, nome }`.
- Ao montar o payload de envio, gravar `origem_tipo`/`origem_id` e
  `destino_tipo`/`destino_id` a partir do item selecionado, em vez de
  `origem_unidade_id`/`destino_unidade_id`.

## Testes

- Feature tests de `LocalidadeController` (CRUD completo, autorização
  admin-only para escrita, leitura para qualquer autenticado, `destroy`
  soft).
- Feature test do endpoint `GET /pontos-viagem` (mistura unidades +
  localidades ativas, ordenação, exclusão de inativos).
- Ajuste de `tests/Feature/Solicitacao/SolicitacaoApiTest.php`: os testes que
  hoje enviam `origem_unidade_id`/`destino_unidade_id` passam a enviar
  `origem_tipo/origem_id` e `destino_tipo/destino_id`; adicionar um caso
  cobrindo origem/destino como `localidade`.
