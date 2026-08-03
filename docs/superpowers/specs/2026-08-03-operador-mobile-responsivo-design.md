# Design: Responsividade mobile para fluxo do operador (FleetApp)

## Problema

Operadores (motoristas) usam o FleetApp no smartphone em campo para Check-in,
Iniciar/Encerrar Viagem e registrar Abastecimento. Esses três fluxos são
formulários renderizados dentro de `components/ui/Modal.jsx`, que tem
`max-h-[90vh]` com um `overflow-y-auto` interno envolvendo tanto os campos
quanto o botão de ação final (`<div className="flex justify-end">`).

Em telas pequenas — especialmente com o teclado virtual aberto, ou em
formulários mais longos como o de Abastecimento (8 campos) — o botão de
submissão fica fora da área visível e só é alcançado rolando até o fim do
modal. Isso viola a regra de "visibilidade absoluta de ações" (nenhuma ação
crítica pode ficar fora da área de rolagem sem alternativa).

Problemas secundários observados nos mesmos formulários:
- Inputs/selects usam `py-2` (~36px de altura), abaixo do touch target mínimo
  recomendado de 48px.
- `AbastecimentoForm` usa `grid-cols-2` fixo, apertando campos em telas
  estreitas (< 375px).
- Navegação do operador no mobile depende do menu hambúrguer (`Sidebar.jsx`),
  escondendo os 1-3 itens permitidos (`/checkins`, `/viagens`,
  `/abastecimentos`) atrás de um toque extra, mesmo havendo poucos itens.

Não há totem físico ainda — o requisito de "totem-ready" é atendido
naturalmente pelo breakpoint mobile full-screen (mesma necessidade de tela
cheia, alto contraste e botões grandes), sem trabalho dedicado a hardware de
totem nesta rodada.

## Escopo

Dentro do escopo:
1. `Modal.jsx` responsivo — full-screen abaixo de `sm` (640px), comportamento
   atual acima disso.
2. Componente novo `FormActionBar` — barra de ação sempre visível (sticky
   bottom), usado nos 3 formulários do operador.
3. Ajuste de touch targets (`py-3`, min-height 48px) e grid responsivo
   (`grid-cols-1` abaixo de `sm`) em `ViagemForm`, `CheckinForm`,
   `AbastecimentoForm`.
4. Componente novo `BottomNav` — navegação inferior fixa, visível apenas para
   `perfil === 'operador'` em telas `< md`, com os mesmos itens/permissões já
   calculados em `Sidebar.jsx` (`rotasPermitidas`).
5. Ajuste em `Layout.jsx` para reservar espaço (`pb-16 md:pb-0`) no `<main>`
   quando o `BottomNav` estiver visível.

Fora do escopo:
- Redesenho visual de Dashboard, Relatórios ou telas de gestor/admin — não
  foram reportadas como quebradas.
- Hardware/fluxo dedicado a totem físico (não existe ainda).
- Mudanças no fluxo de autenticação ou nas regras de negócio dos formulários.

## Arquitetura e componentes

### `Modal.jsx`
- Mantém a mesma API pública (`open`, `onClose`, `title`, `children`, `size`).
- Abaixo de `sm`: container passa a `fixed inset-0 h-[100dvh] w-full` sem
  `rounded-2xl`, sem `p-4` externo, sem `max-h-[90vh]` (ocupa a viewport
  inteira). `h-[100dvh]` (dynamic viewport height) evita que o teclado virtual
  do celular comprima ou esconda o rodapé.
- Estrutura interna continua em 3 regiões: header fixo, conteúdo
  `flex-1 overflow-y-auto`, e rodapé — o rodapé passa a ser onde o
  `FormActionBar` de cada formulário se ancora via `sticky bottom-0`.
- Acima de `sm`: comportamento inalterado (modal centralizado,
  `max-h-[90vh]`, `sizeMap`).

### `FormActionBar` (novo, `components/ui/FormActionBar.jsx`)
- Props: `children` (os botões de ação).
- Renderiza `<div className="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex justify-end gap-3">`.
- Botões dentro dele devem ter `min-h-[48px]` — ajuste feito diretamente nos
  3 formulários que o consomem (o componente não impõe estilo aos filhos).
- Substitui o atual `<div className="flex justify-end">` ao final de
  `ViagemForm`, `CheckinForm`, `AbastecimentoForm`.

### Formulários do operador (`ViagemForm`, `CheckinForm`, `AbastecimentoForm`)
- Trocar `py-2` por `py-3` em todos os `input`/`select`.
- `AbastecimentoForm`: `grid-cols-2` → `grid-cols-1 sm:grid-cols-2`.
- Rodapé de submit passa a usar `<FormActionBar>`.
- Nenhuma mudança de validação, mutation ou lógica de negócio.

### `BottomNav` (novo, `components/layout/BottomNav.jsx`)
- Só renderiza quando `isOperador && window width < md` (via Tailwind
  `md:hidden`, sem JS de detecção — o componente sempre monta mas fica
  oculto via CSS acima de `md`).
- Recebe a mesma lista de itens permitidos que `Sidebar.jsx` já calcula
  (`rotasPermitidas` baseado em `checkinAtivo`) — para não duplicar a regra,
  essa lista sai de um hook/helper compartilhado extraído de `Sidebar.jsx`
  (ex: `useOperadorRotasPermitidas()`), consumido por ambos.
- Cada item: ícone (mesmos `lucide-react` já usados no Sidebar) + label
  curto, área de toque mínima 48x48px, item ativo destacado (cor de marca).
- `fixed bottom-0 inset-x-0 z-30 md:hidden`, com `safe-area-inset-bottom`
  (`env(safe-area-inset-bottom)`) para não colidir com a barra de gestos do
  iOS.

### `Sidebar.jsx`
- Sem mudança na lógica de permissões — só extrai `rotasPermitidas` do
  operador para o hook compartilhado citado acima.
- Continua sendo a navegação em `md:` e acima para todos os perfis, e em
  mobile para admin/gestor (que têm mais itens e não se beneficiam de uma
  bottom bar de 2-3 slots).

### `Layout.jsx`
- `<main>` ganha `pb-16 md:pb-0` quando `isOperador` (para não ficar atrás do
  `BottomNav` em mobile).
- Renderiza `<BottomNav />` (o próprio componente decide, via `isOperador` +
  CSS, se aparece).

## Testes

- Sem mudança em regras de negócio/validação — não são necessários novos
  testes de backend.
- Teste manual (não há suite de teste de UI configurada no projeto):
  1. Abrir cada um dos 3 formulários do operador em viewport mobile
     (< 640px) com DevTools, confirmar que o botão de ação permanece visível
     sem rolar, inclusive com o teclado virtual simulado aberto.
  2. Confirmar que em desktop/tablet (≥ 640px) o modal mantém a aparência
     atual (centralizado, cantos arredondados).
  3. Confirmar que `BottomNav` aparece só para perfil operador em mobile, com
     os itens corretos conforme `checkinAtivo` (1 item sem checkin, 3 itens
     com checkin ativo), e que o item ativo é destacado.
  4. Confirmar que gestor/admin em mobile continuam vendo o hambúrguer e o
     `Sidebar` normalmente, sem `BottomNav`.
