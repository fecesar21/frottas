# Especificação do Frontend — Health Drive / FleetCore

> Documento gerado a partir da leitura do código-fonte em `resources/js`, `resources/solicitacao-js`, `resources/css`, `index.html`, `solicitacao.html`, `vite.config.js`, `tailwind.config.js`, `public/manifest.json` e `public/sw.js` (23/09/2026).
>
> **Observação:** o `CLAUDE.md` diz que Vite/Tailwind estão "sem uso" e que o projeto é só API. Isso está **desatualizado**: existem duas SPAs React completas, compiladas pelo Vite para `public/`.

---

## 1. Visão geral da arquitetura

O frontend é composto por **duas SPAs independentes** no mesmo repositório, compiladas num único build Vite multi-entrada:

| SPA | Entrada HTML | Código-fonte | URL base | Público | Autenticação |
|---|---|---|---|---|---|
| **Painel de Gestão de Frota** | `index.html` | `resources/js/` | `/` | Admin, Gestor, Operador (motorista) | CPF + senha numérica → `POST /api/auth/login` |
| **Solicitação de Transporte** | `solicitacao.html` | `resources/solicitacao-js/` | `/solicitar` | Solicitantes (colaboradores dos hospitais) | Usuário/senha de rede (AD/LDAP) → `POST /api/auth/login-ad` |

As duas compartilham o CSS (`resources/css/app.css`) e o tema Tailwind, mas têm **contextos de autenticação, instâncias axios e chaves de `localStorage` isolados**.

### Como são servidas

- **Build:** `vite build` gera `public/index.html`, `public/solicitacao.html` e `public/assets/*` (hash no nome).
- **Painel:** servido como `public/index.html` pelo Nginx.
- **Solicitação:** `routes/web.php` tem um catch-all `GET /solicitar/{any?}` que devolve `public/solicitacao.html`, permitindo refresh em sub-rotas do react-router.
- **Dev:** `npm run dev` (Vite na porta 5173) com proxy de `/api` para `http://localhost:8000`.
- **Deploy:** `scripts/deploy.sh` executa `npm run build`. Um `git pull` sozinho **não** atualiza o frontend.

---

## 2. Stack tecnológica

### 2.1 Dependências de runtime (`package.json`)

| Biblioteca | Versão | Uso no projeto |
|---|---|---|
| **react** / **react-dom** | ^18.3.1 | UI; `createRoot` + `StrictMode` |
| **react-router-dom** | ^6.26.2 | `BrowserRouter`, `Routes`, `Outlet`, `NavLink`, `Navigate`, `useSearchParams`; rotas aninhadas (`/relatorios/*`) e `basename="/solicitar"` |
| **@tanstack/react-query** | ^5.56.2 | Todo o estado de servidor do painel: `useQuery`, `useMutation`, `invalidateQueries`, polling via `refetchInterval`. Padrão global: `retry: 1`, `staleTime: 30s` |
| **axios** | ^1.7.4 | Cliente HTTP (`baseURL: '/api'`), interceptors de token Bearer e de 401 |
| **react-hook-form** | ^7.53.0 | Instalado, mas **não usado**: os formulários usam `useState` controlado |
| **date-fns** | ^3.6.0 | Manipulação de datas (calendário de escalas e filtros) |
| **recharts** | ^3.10.1 | Gráficos do Dashboard (Line, Bar, Pie) |
| **leaflet** + **react-leaflet** | ^1.9.4 / ^4.2.1 | Mapa do trajeto da viagem (`MapContainer`, `TileLayer`, `Polyline`, `CircleMarker`) |
| **lucide-react** | ^0.441.0 | Todos os ícones |

### 2.2 Dependências de build

| Ferramenta | Versão | Configuração |
|---|---|---|
| **vite** | ^6.0.11 | `root: '.'`, `publicDir: 'public_assets'`, `outDir: 'public'`, `emptyOutDir: false`, `rollupOptions.input = { main, solicitacao }` |
| **@vitejs/plugin-react** | ^4.3.1 | JSX + Fast Refresh |
| **tailwindcss** | ^3.4.13 | Estilização (utility-first) |
| **postcss** + **autoprefixer** | ^8.4 / ^10.4 | Pipeline CSS |

- **Linguagem:** JavaScript (ESM, `"type": "module"`) com JSX. **Sem TypeScript.**
- **Sem** biblioteca de componentes (MUI, shadcn etc.): os componentes são próprios.
- **Sem** linter/formatter JS configurado e **sem testes de frontend**.

### 2.3 APIs nativas do navegador usadas

| API | Onde | Finalidade |
|---|---|---|
| `navigator.geolocation.watchPosition` | `hooks/useRastreamento.js` | Rastreamento GPS da viagem em andamento |
| **IndexedDB** (`fleetcore-rastreamento` / `pontos_pendentes`) | `lib/pontosQueue.js` | Fila offline de pontos GPS não enviados |
| **Service Worker** (`/sw.js`) + **Background Sync** (`sync-viagem-pontos`) | `main.jsx`, `useRastreamento.js` | Cache/PWA e reenvio de pontos em segundo plano |
| **Screen Wake Lock** (`navigator.wakeLock`) | `useRastreamento.js` | Manter a tela ligada durante a viagem |
| **Web Audio API** (`AudioContext`) | `hooks/useNotificacoes.js` | "Beep" de 880 Hz ao chegar notificação nova |
| `localStorage` | Auth e notificações | Token, usuário, IDs de notificações já exibidas |
| `URL.createObjectURL` | `utils/downloadBlob.js` | Download de relatórios em PDF |
| `FormData` multipart | `api/checklistVeiculo.js` | Upload de foto no checklist do veículo |

---

## 3. Design system

### 3.1 Tema Tailwind (`tailwind.config.js`)

- **Fonte:** `Figtree` + fallback sans padrão.
- **Paleta `brand` (teal):** 50 `#eefcfb` · 100 `#d5f5f3` · 200 `#afecea` · 300 `#77dee0` · 400 `#3dc8cf` · **500 `#1aabb3`** · 600 `#158890` · 700 `#146f77` · 800 `#155962` · 900 `#164952` · 950 `#072c33`
- **Paleta `navy`:** 700 `#1e2a3b` · 800 `#162030` · 900 `#0f1623` · 950 `#090e18`
- **Animações:** `animate-fade-in` (opacidade + translateY de 8px, 0,3s) e `animate-slide-in` (translateX de −12px, 0,25s).

### 3.2 CSS global (`resources/css/app.css`)

- `--sidebar-w: 240px`; transição de cores de 150ms em todos os elementos; `antialiased`.
- Scrollbar fina (5px, thumb `gray-300`).
- Utilitários: `.bg-dot-pattern` (grade de pontos no login), `.sidebar-glow`, `.card-accent-{brand|green|yellow|red|gray}` (borda superior de 3px).

### 3.3 Linguagem visual

- **Telas de autenticação:** gradiente `navy-950 → navy-900 → brand-950`, grade de pontos, blobs desfocados, card branco translúcido `rounded-3xl` com faixa superior em gradiente brand.
- **Layout interno:** sidebar escura (gradiente navy) de 240px e conteúdo `bg-slate-50`. Header branco translúcido com `backdrop-blur`, sticky.
- **Cards/tabelas:** `bg-white rounded-2xl shadow-sm border-gray-100`.
- **Botões primários:** gradiente `brand-500 → brand-700`, `rounded-xl`.
- **Responsividade:** mobile-first. A sidebar vira drawer com overlay abaixo de `md`; as tabelas usam `overflow-x-auto`; os grids vão de 2 para 4 colunas (`lg`).

### 3.4 Gráficos (`components/charts/chartTheme.js`)

- Paleta categórica: `#2a78d6` azul, `#eb6834` laranja, `#1baf7a` aqua, `#eda100` amarelo, `#e87ba4` magenta, `#008300` verde, `#4a3aa7` violeta, `#e34948` vermelho.
- Formatadores: `fmtNumero` (pt-BR), `fmtBrl` (R$), `fmtMinutos` ("Xh Ymin").
- `PieChartCard` limita a 7 fatias (`MAX_FATIAS`) e agrupa o restante.

### 3.5 Componentes de UI reutilizáveis (`components/ui`)

| Componente | Props | Descrição |
|---|---|---|
| `Alert` | `type` (success/error/warning/info), `message` | Caixa com ícone; não renderiza nada se `message` estiver vazio |
| `Badge` | `value` | Converte status/perfil em rótulo pt-BR com cor (ver §8) |
| `Card` | `title, value, sub, icon, color` | KPI com ícone, borda de destaque e hover com elevação |
| `LoadingSpinner` | `text` | Spinner brand centralizado |
| `Modal` | `open, onClose, title, size` (sm/md/lg/xl) | Overlay com blur, fecha com ESC ou clique fora, altura máxima de 90vh com rolagem |

Compartilhados: `MotoristaSelect` (motoristas ativos, exibe "Nome — CNH X") e `VeiculoSelect` (filtro opcional por status, exibe "Placa — Modelo (status)").

---

## 4. Camada de dados e autenticação (Painel)

### 4.1 Cliente HTTP (`api/axios.js`)
- `baseURL: '/api'`, headers JSON.
- Request: injeta `Authorization: Bearer <hd_token>`.
- Response: em **401** (exceto `/auth/login`), limpa `hd_token`/`hd_user` e redireciona para `/login`.

### 4.2 Módulos de API (`resources/js/api/*.js`)

| Módulo | Endpoints |
|---|---|
| `auth` | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `POST /auth/esqueci-senha`, `POST /auth/redefinir-senha` |
| `veiculos` | CRUD `/veiculos`, `PATCH /veiculos/{id}` (status) |
| `motoristas` | CRUD `/motoristas`, `PATCH` status, `GET /motoristas/alertas/cnh`, `/motoristas/disponiveis`, `/motoristas/sem-usuario` |
| `escalas` | `GET/POST /escalas`, `DELETE /escalas/{id}`, `POST /escalas/semana` |
| `checkins` | `GET/POST /checkins`, `GET /checkins/{id}`, `PATCH /checkins/{id}/checkout` |
| `checklistVeiculo` | `GET /checklist-veiculo/pendente`, `GET /{id}`, `POST /{id}/item` (multipart com `_method=PATCH`), `PATCH /{id}/enviar` |
| `plantao` | `GET/POST /plantao`, `GET /plantao/{id}`, `GET /plantao/modelo/itens`, `PATCH /{id}/item`, `PATCH /{id}/finalizar` |
| `viagens` | CRUD `/viagens`, `PATCH /{id}/chegada`, `POST/GET /{id}/pontos` |
| `abastecimentos` | `GET/POST /abastecimentos`, `GET /abastecimentos/resumo`, `DELETE /{id}` |
| `km` | `GET/POST /km` |
| `solicitacoes` | `GET /solicitacoes`, `GET /{id}`, `PATCH /{id}/aceitar`, `/cancelar`, `/motorista-aceitar` (km_saida opcional), `/motorista-recusar` (motivo) |
| `notificacoes` | `GET /notificacoes/nao-lidas`, `POST /notificacoes/marcar-lidas` |
| `relatorios` | `/relatorios/dashboard`, `/dashboard/graficos`, `/abastecimentos[/pdf]`, `/viagens[/pdf]`, `/plantao[/pdf]`, `/motoristas[/pdf]`, `/checkins`, `/checklist-veiculo` |
| `usuarios` | CRUD `/usuarios` |
| `unidades` | CRUD `/unidades`, `POST/DELETE /unidades/{id}/motoristas[/{mid}]`, `POST/DELETE /unidades/{id}/veiculos[/{vid}]` |
| `unidadeLdapConfig` | `GET/PUT/DELETE /unidades/{id}/ldap-config`, `POST /unidades/{id}/ldap-config/testar` |
| `localidades` | CRUD `/localidades` (`PATCH` para atualizar, `DELETE` para desativar) |

### 4.3 `AuthContext` (Painel)
- Estado: `user`, `checkinAtivo` (persistidos em `localStorage.hd_user`).
- Flags derivadas: `isAdmin` (`perfil === 'admin'`), `isGestor` (admin ou gestor), `isOperador`.
- Ao carregar a página, se o usuário for operador, chama `GET /auth/me` para ressincronizar o `checkin_ativo`.
- O `login` não navega diretamente: a tela de Login redireciona de forma reativa quando `user` muda, evitando condição de corrida com o `PrivateRoute`.

### 4.4 Controle de acesso por rota

| Guarda | Regra |
|---|---|
| `PrivateRoute` | Sem usuário → `/login`. **Operador sem check-in:** só `/checkins`. **Operador com check-in:** `/checkins`, `/viagens`, `/abastecimentos`. Outras rotas redirecionam. |
| `GestorRoute` | Admin ou gestor (usada em `/solicitacoes`) |
| `AdminRoute` | Só admin (`/usuarios`, `/unidades`, `/configuracoes/*`) |
| Bloqueio por checklist | Operador com check-in ativo e checklist do veículo com status diferente de `enviado` → tela cheia modal obrigatória (`ChecklistVeiculoModal`) |

### 4.5 Chaves de cache do React Query (principais)
`dashboard`, `dashboard-graficos/[mes,ano]`, `veiculos/[status]`, `motoristas/[status|ativos|disponiveis]`, `motoristas-disponiveis`, `escalas/[de,ate]`, `checkins/[status]`, `checklist-veiculo/[pendente|bloqueio]`, `plantao/[id]`, `viagens/[status|em_andamento|ativa-motorista]`, `viagem-pontos/[id]`, `solicitacoes/[fila-motorista|fila-motorista-lista]`, `abastecimentos/[resumo]`, `relatorio-*`, `usuarios`, `unidades`, `unidade/[id]`, `unidade-ldap-config/[id]`, `localidades`, `notificacoes/nao-lidas`.

### 4.6 Polling (tempo real sem WebSocket)

| Dado | Intervalo |
|---|---|
| Solicitações (lista do gestor) | 10s |
| Notificações não lidas | 20s |
| Viagem em andamento (operador, rastreamento global) | 30s |
| Pontos GPS no mapa (viagem em andamento) | 30s |
| Fila do motorista (`aguardando_finalizacao_trajeto`) | 30s |
| Dashboard (KPIs e gráficos) | 60s |

---

## 5. Layout global do Painel

- **Sidebar** (`components/layout/Sidebar.jsx`), com seções:
  - PRINCIPAL: Dashboard
  - FROTA: Veículos, Motoristas
  - OPERAÇÕES: Check-ins, Viagens, Abastecimentos, Solicitações de Transporte (somente gestor)
  - ANÁLISES: Relatórios
  - ADMIN (somente admin): Usuários, Unidades, Configurações
  - Rodapé: avatar com iniciais, nome, perfil e botão **Sair**.
  - Para operador, só aparecem os itens permitidos pelo estado do check-in.
- **Header:** título da página (mapa `pageTitles`), botão de menu no mobile, sino de notificações (admin/gestor) com badge (9+), pulso e painel dropdown (fecha com clique fora ou ESC), avatar, primeiro nome e perfil.
- **Pop-ups de notificação:** enfileirados (`pendentes`), um por vez:
  - `NovaSolicitacaoPopup` (gestor): toast que some em 6s e modal que fecha sozinho em 60s.
  - `NovaViagemDesignadaPopup` (motorista): aceitar (pede KM de saída via `KmSaidaModal`) ou recusar (`MotivoRecusaModal`).
  - Os IDs já exibidos ficam em `localStorage.notificacoes_popup_anunciados_<userId>` (máximo de 200).
- **Faixas de status do GPS** (operador): verde "Rastreando viagem — N ponto(s) pendente(s)" ou âmbar "GPS: <erro>".

---

## 6. Cenários do Painel (tela a tela)

### 6.1 Login — `/login`
- Campos: **CPF** (máscara `000.000.000-00`, `inputMode=numeric`; os dígitos são extraídos antes do envio no campo `usuario`) e **Senha** (`type=password`, `pattern=[0-9]*`, `minLength=6`, **apenas numérica**).
- Link "Esqueceu a senha?". Erro mostrado a partir de `response.data.error`.
- Redirecionamento após login: operador → `/viagens` (com check-in) ou `/checkins` (sem check-in); demais perfis → `/`.

### 6.2 Esqueci a senha — `/esqueci-senha`
- Campo e-mail → `POST /auth/esqueci-senha`. A mensagem de sucesso é neutra ("Se o e-mail estiver cadastrado…"), para não revelar quais e-mails existem.

### 6.3 Redefinir senha — `/redefinir-senha?email=&token=`
- Lê `email` e `token` da query string; sem eles, mostra "link inválido".
- Nova senha e confirmação (numéricas, mínimo de 6) com validação de igualdade no cliente. Após sucesso, redireciona para `/login` em 2,5s.

### 6.4 Dashboard — `/`
- Banner de boas-vindas com o primeiro nome e "Atualizado a cada minuto".
- **KPIs de Veículos:** Disponíveis, Em uso, Manutenção, Total.
- **KPIs de Operações:** Check-ins ativos, Motoristas ativos (com total), KM no mês, Combustível no mês (R$).
- **Análise gráfica** com filtro Mês/Ano (`MonthYearFilter`):
  - Linha: Total de abastecimento mês a mês (R$)
  - Barras verticais: Total de KM mês a mês
  - Pizza: Viagens por motivo; KM por motorista
  - Barras horizontais: Quantidade de viagens por motorista; Tempo médio de viagem por motorista
- Alerta âmbar: **CNH vencendo nos próximos 30 dias**, com os nomes em chips.

### 6.5 Veículos — `/veiculos`
- Filtro por status. Colunas: Placa, Modelo, Marca, Ano, Combustível, KM Atual, Status, **Ativo** (toggle), **Manutenção** (toggle, mostra a data de início), ações.
- Regras dos toggles: ativo/inativo alterna entre `disponivel` e `inativo`; manutenção alterna entre `disponivel` e `manutencao`. Ambos ficam desabilitados com o veículo `em_uso`, e a manutenção também quando `inativo`.
- **VeiculoForm** (modal): Placa\*, Modelo, Marca, Ano, Combustível, Observações, além do vínculo com **Unidades** por checkboxes (vincula/desvincula na hora via API de unidades).

### 6.6 Motoristas — `/motoristas`
- Filtro por status. Colunas: Nome, CPF, CNH, Cat., Validade CNH, Turno, Status, Ativo (toggle), ações. Linhas inativas com opacidade de 60%.
- **MotoristaForm:** Nome\*, CPF\*, Telefone, E-mail, CNH\*, Categoria\* (A, B, C, D, E, AB, AC, AD, AE), Validade CNH\* (date), Turno padrão (dia/noite), Observações, vínculo com Unidades (checkboxes).

### 6.7 Escalas — `/escalas`
- Calendário semanal/período (`de`/`ate`) com linhas por turno (**dia/noite**) e motoristas alocados; remoção individual.
- Modal "Adicionar escala": Motorista\*, Data\*, Turno\* (dia, noite, folga).
- *Rota ativa, mas sem item no menu lateral.*

### 6.8 Check-ins — `/checkins`
- Filtro por status. Colunas: Motorista, Veículo, Turno, KM saída, KM retorno, Check-in, Check-out, Status, ações. Layout em cards no mobile e tabela no desktop.
- **Novo check-in:** Motorista\*, Veículo\*, Turno\* (dia/noite), KM saída\*, Nível de combustível na saída (0–100%). Ao salvar, invalida `checkins`, `veiculos` e `checklist-veiculo`, o que dispara o checklist obrigatório para o operador.
- **Encerrar check-in (checkout):** KM retorno (mínimo = KM de saída), Nível de combustível no retorno, Ocorrências.

### 6.9 Checklist do veículo (modal obrigatório)
- Carrega `GET /checklist-veiculo/pendente`; itens agrupados por **categoria**.
- Cada item pode ser marcado **Conforme** ou **Não conforme**; clicar de novo no mesmo botão desmarca (envia `conforme = null`).
- Itens com `requer_valor` pedem um valor numérico dentro de `valor_min`–`valor_max` (padrão 0–300).
- Não conforme: observação, valor e **foto** (`input file accept="image/*"`, enviada como multipart).
- Botão **Enviar** → `PATCH /enviar`. Se já estiver enviado: "Checklist do veículo já foi enviado hoje."

### 6.10 Passagem de Plantão — `/plantao`
- Colunas: Data, Veículo, Saindo, Entrando, KM, Itens OK, Pendências, Status, ações.
- **PlantaoForm:** Motorista saindo, Motorista entrando, Veículo\*, KM no momento\*, Nível de combustível (%).
- **PlantaoChecklist** (modal xl): cada item com resultado **OK / Pendência / N/A** (padrão `na`), contadores de OK e pendências, botão **Finalizar** (depois de finalizado, fica somente leitura).
- *Rota ativa e atalho no manifest PWA, mas sem item no menu lateral.*

### 6.11 Viagens — `/viagens`
- Filtro por status. Colunas: Motorista, Veículo, Origem → Destino, Saída, Chegada, KM percorrido, Status, ações (Registrar chegada, Ver trajeto).
- O operador vê em destaque a viagem ativa e o card da **fila do motorista** (`FilaMotoristaCard`: solicitações `aguardando_finalizacao_trajeto`, aceitas com KM de saída).
- **ViagemForm:** Motorista\*, Veículo\*, Origem\*, Destino\*, Motivo\* (6 motivos, ver §8), Nº do atendimento (0–999999, obrigatório quando o motivo é transferência de paciente), KM saída\*.
- **Registrar chegada:** KM chegada\* (mínimo = KM de saída), Observações.
- **ViagemDetalhe:** mapa Leaflet com **Polyline** azul `#3b82f6` do trajeto e `CircleMarker` de início e fim, atualizado a cada 30s enquanto a viagem estiver em andamento.

### 6.12 Rastreamento GPS (global, operador)
- Ativo sempre que há viagem `em_andamento` do operador, em qualquer tela.
- `watchPosition` com `enableHighAccuracy`. Descarta leituras com precisão pior que **60 m** e deslocamentos menores que **25 m** (distância calculada por Haversine).
- Se o envio falhar, o ponto vai para a fila IndexedDB e é registrado um Background Sync. A fila é reenviada a cada 15s, no evento `online` e na mensagem `FLUSH_PONTOS_PENDENTES` do service worker, e para ao receber 422 (viagem já encerrada).
- Wake Lock da tela e mensagens de erro em pt-BR (permissão negada, indisponível, timeout, HTTPS necessário).

### 6.13 Abastecimentos — `/abastecimentos`
- Cards de resumo e tabela: Data, Veículo, Motorista, Posto, Combustível, Litros, R$/L, Total, KM, excluir.
- **AbastecimentoForm:** Motorista\*, Veículo\*, Posto, Combustível\*, Litros\* (passo 0,01), Preço por litro\* (passo 0,001), KM no momento\*, Nota fiscal.

### 6.14 Solicitações de Transporte (gestor) — `/solicitacoes`
- Atualização a cada 10s. Colunas: Data, Solicitante, Motivo, Detalhe, Saída, Chegada, Status, Motorista, ação.
- Ações: **Aceitar** (status `aberto`) ou **Redesignar** (status `recusada`, exibindo o motivo da recusa). O modal pede Motorista\* (lista de disponíveis) e Veículo\*.
- Fluxo: o gestor designa → o motorista recebe o pop-up `NovaViagemDesignada` → aceita (informando KM de saída) ou recusa (informando motivo). Se o motorista já estiver em trajeto, a solicitação vai para `aguardando_finalizacao_trajeto`.

### 6.15 Relatórios — `/relatorios/*`
Abas por `NavLink` (rotas aninhadas; o índice redireciona para `abastecimentos`):

| Aba | Filtros | Colunas | PDF |
|---|---|---|---|
| Abastecimentos | De/Até | Data, Placa, Motorista, Posto, Combustível, Litros, R$/L, Total, KM | ✔ |
| Viagens | De/Até | Saída, Chegada, Placa, Motorista, Origem → Destino, Motivo, Nº Atendimento, KM perc., Duração, Status | ✔ |
| Checklist de Veículo | Veículo, Data, Status (pendente/enviado) | Data, Motorista, Veículo, Status, Conforme, Não conforme, detalhes (modal "Itens não conformes") | — |
| Motoristas | — | Nome, CNH, Cat., Validade, Turno, Viagens, KM total, Abastec., Plantões, Status, situação da CNH | ✔ |
| Plantão | De/Até | Data, Placa, Saindo, Entrando, KM, OK, Pendência, Duração, Status | ✔ (*rota existe, mas sem aba visível*) |

Os PDFs são baixados via `responseType: 'blob'` + `downloadBlob()`.

### 6.16 Usuários (admin) — `/usuarios`
- Colunas: Nome, E-mail, Perfil (Badge), Ativo, Último acesso, Criado em, ações (editar/desativar).
- **UsuarioForm:** Nome\*, CPF\*, E-mail, Senha, Perfil\* (operador/gestor/admin), Unidade (apenas ativas), **Motorista vinculado\*** (só para operador; lista motoristas sem usuário e mantém o atual na edição), Ativo.

### 6.17 Unidades (admin) — `/unidades` e `/unidades/:id`
- Lista com editar, desativar e reativar. **UnidadeForm:** Nome\*, Tipo\* (matriz/filial), Ativa.
- **UnidadeDetalhes:** duas tabelas, Motoristas (Nome, CPF, Turno, Status) e Veículos (Placa, Modelo, Marca, Status), com desvincular e um modal de vínculo em lote por checkboxes.

### 6.18 Configurações (admin) — `/configuracoes`
- Hub com cards: **LDAP por Unidade** e **Localidades**.
- **`/configuracoes/ldap`:** escolha da unidade e `UnidadeLdapConfigForm`: Host do DC\*, Porta\*, LDAPS (SSL), StartTLS, Base DN\*, Usuário da conta de serviço\*, Senha, Atributo AD de unidade, Valores do atributo, Ativa. Botões **Testar conexão** (`POST /testar`) e **Salvar** (`PUT`). Um 404 é tratado como "sem configuração".
- **`/configuracoes/localidades`:** lista com editar, desativar e reativar. **LocalidadeForm:** Nome\*, Endereço, Latitude, Longitude, Telefone, E-mail, Ativa. As localidades aparecem como origem/destino nas solicitações.

---

## 7. SPA de Solicitação de Transporte (`/solicitar`)

### 7.1 Infraestrutura
- `BrowserRouter basename="/solicitar"`. **Não usa React Query**: usa `useEffect` + `useState`.
- Axios próprio com token `hd_solicitacao_token`; em 401, limpa e redireciona para `/solicitar/login`.
- `AuthContext` mínimo (`user`, `login`, `logout`) em `hd_solicitacao_user`.
- Layout: sidebar navy ("Solicitar Transporte") com **Nova Solicitação** e **Minhas Solicitações**; topbar no mobile; conteúdo com `max-w-3xl`.
- Não registra service worker nem tem manifest.

### 7.2 Login — `/solicitar/login`
- "Usuário de rede" e "Senha de rede" (texto livre) → `POST /api/auth/login-ad` (LDAP da unidade, via `samaccountname`).

### 7.3 Nova Solicitação — `/solicitar/`
- **Motivo\*** em cards de rádio (`has-[:checked]` realça o selecionado).
- Campos condicionais:

| Motivo | Campos extras |
|---|---|
| `transferencia_paciente` | Origem\*, Destino\*, Nº do atendimento\* (até 6 dígitos) |
| `transporte_colaborador` | Origem\*, Destino\* |
| `buscar_medico` | Cidade\* |
| `material_outro_hospital` | Para qual hospital?\* |
| `buscar_material_fornecedor` | Nome do fornecedor\* |
| `tfd` | — |

- Origem e destino vêm de `GET /api/pontos-viagem` (Unidades e Localidades juntas). Ao escolher, o formulário preenche `*_id` e `*_tipo` (relação polimórfica).
- Observações (opcional). Erros de validação 422 aparecem por campo. Após o sucesso, o formulário é limpo e a página rola para o topo.

### 7.4 Minhas Solicitações — `/solicitar/minhas-solicitacoes`
- Tabela: Data, Motivo, Saída, Chegada, Status (pill colorida), Motorista.

---

## 8. Enumerações e rótulos

**Motivos de viagem:** `transferencia_paciente` (Transferência de Paciente) · `buscar_medico` (Buscar Médicos em outra cidade) · `material_outro_hospital` (Levar Material em outro Hospital) · `transporte_colaborador` (Transporte de Colaborador(es)) · `buscar_material_fornecedor` (Buscar materiais em fornecedor) · `tfd` (TFD).

**Status (Badge):**
- Veículo: `disponivel`, `em_uso`, `manutencao`, `inativo`
- Motorista: `ativo`, `inativo`
- Viagem: `em_andamento`, `concluida`, `cancelada`
- Check-in: `ativo`, `finalizado`
- Checklist: `pendente`, `enviado`
- Item de plantão: `ok`, `pendencia`, `na`
- CNH: `vencendo`, `vencida`
- Solicitação: `aberto`, `em_trajeto`, `aguardando_finalizacao_trajeto`, `recusada`, `finalizado`, `cancelado`

**Perfis:** `admin`, `gestor`, `operador` (motorista, vinculado via `motorista_id`); solicitante (somente SPA `/solicitar`).

**Turnos:** `dia`, `noite` (escala aceita também `folga`). **Tipos de unidade:** `matriz`, `filial`. **Categorias de CNH:** A, B, C, D, E, AB, AC, AD, AE.

---

## 9. PWA (Painel)

- `public/manifest.json`: nome "FleetCore — Controle de Frotas", `display: standalone`, `orientation: portrait-primary`, `theme_color #4d8ef5`, `background_color #0f1117`, ícones de 72 a 512px (`any maskable`), atalhos **Passagem de Plantão** (`/plantao`) e **Nova Viagem** (`/viagens`).
- `public/sw.js` (`CACHE_NAME = fleetcore-v3`):
  - `/api/*`: sempre rede; offline, responde 503 com JSON `{ error: 'Sem conexão com o servidor.' }`.
  - Navegação: rede primeiro, com cache como fallback (garante o build novo após deploy).
  - Assets com hash: cache primeiro.
  - `skipWaiting` + `clients.claim`; mensagens para atualização e para o flush de pontos GPS.

---

## 10. Pontos de atenção encontrados

1. **CLAUDE.md desatualizado** quanto ao frontend (diz "sem uso").
2. **Senha só numérica** no Login e em Redefinir senha (`pattern=[0-9]*`): senhas alfanuméricas não passam pela validação do navegador.
3. `react-hook-form` instalado e não usado.
4. `/plantao`, `/escalas` e a aba de relatório de plantão têm rota, mas não aparecem na navegação. O operador não consegue acessar `/plantao`, apesar do atalho no PWA.
5. Divergência de marca: "Health Drive" no HTML e na UI, "FleetCore" no manifest e no service worker; `theme-color #4d8ef5` (azul) diferente da paleta brand (teal `#1aabb3`).
6. `tailwind.config.js` define a fonte `Figtree`, mas não há `<link>` que a carregue; o `sw.js` faz cache de *DM Sans/DM Mono*, que não são usadas.
7. Os selects compartilhados usam `focus:ring-blue-500`, enquanto o resto usa `brand-400`.
8. Não há testes automatizados nem lint de frontend.
9. O interceptor 401 da SPA de solicitação ignora `/auth/login`, mas o login real é `/auth/login-ad`: como o `AuthController` responde **401** para credenciais inválidas, uma senha errada recarrega a página de login em vez de mostrar a mensagem de erro.
