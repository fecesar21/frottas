# Design: Responsividade mobile do app de solicitação (`resources/solicitacao-js`)

## Problema

O app de solicitação de transporte (usado por solicitantes em `resources/solicitacao-js`)
tem seu `Layout.jsx` estruturado como `<div className="flex"><aside className="w-60
shrink-0">...</aside><main>...</main></div>`, com a sidebar sempre visível e sem
nenhum mecanismo de toggle. Em smartphone, os 240px fixos da sidebar espremem o
conteúdo (formulário de "Nova Solicitação" e tabela de "Minhas Solicitações"),
cortando a tela.

O app de motoristas/gestão (`resources/js`) já resolveu esse mesmo problema: seu
`Sidebar.jsx` é um drawer off-canvas em mobile (`fixed` + `-translate-x-full` /
`translate-x-0` + overlay), acionado por um botão hambúrguer no `Header`, e vira
sidebar fixa normal a partir do breakpoint `md:` (`md:sticky md:translate-x-0`).
Esse padrão será replicado no app de solicitação.

Problemas secundários observados nas duas páginas do app:
- `NovaSolicitacao.jsx`: o grid `grid-cols-2` de Origem/Destino aperta os selects
  em telas estreitas (< 375px).
- `MinhasSolicitacoes.jsx`: a tabela de 6 colunas não tem rolagem horizontal
  própria — em mobile ela força a página inteira a rolar lateralmente.

## Escopo

Dentro do escopo:
1. `Layout.jsx` — sidebar responsiva (drawer em mobile, fixa em `md:+`), com botão
   hambúrguer visível apenas em mobile.
2. `NovaSolicitacao.jsx` — grid responsivo no par Origem/Destino.
3. `MinhasSolicitacoes.jsx` — tabela com rolagem horizontal isolada.

Fora do escopo:
- Mudanças de lógica de negócio, validação ou autenticação.
- Redesenho visual além do necessário para eliminar o corte de conteúdo.
- Componentes novos compartilhados entre os dois apps (`resources/js` e
  `resources/solicitacao-js` permanecem independentes, sem extração de código
  comum nesta rodada).

## Arquitetura e componentes

### `Layout.jsx`
- A sidebar (`<aside>`) passa a ter estado de abertura (`useState`) controlado
  localmente no próprio `Layout` (o app é pequeno o bastante para não precisar
  de contexto dedicado).
- Classes da `<aside>`: `fixed top-0 left-0 z-40 h-screen w-60 flex flex-col ...
  transform transition-transform duration-300 ease-in-out md:sticky md:top-0
  md:translate-x-0 md:z-auto md:shrink-0 ${open ? 'translate-x-0' :
  '-translate-x-full'}` — mesma técnica do `Sidebar.jsx` de `resources/js`.
- Overlay: `{open && <div className="fixed inset-0 z-30 bg-navy-950/70
  backdrop-blur-sm md:hidden" onClick={() => setOpen(false)} />}`.
- Um botão `X` dentro do cabeçalho da sidebar fecha o drawer em mobile (mesmo
  padrão do app de motoristas); cada `NavLink` também fecha o drawer ao navegar
  (`onClick={() => setOpen(false)}`).
- Como hoje não existe nenhum header no `<main>`, adiciona-se uma barra superior
  simples visível só em mobile (`md:hidden`) com o botão hambúrguer (ícone
  `Menu` do lucide-react) que abre o drawer — não é um `Header` completo como em
  `resources/js`, só o necessário para acessar o menu.
- `<main>` perde a dependência de a sidebar estar "encolhendo" o espaço via
  `flex`; passa a ocupar a largura total abaixo da barra mobile, mantendo
  `max-w-3xl mx-auto` para o conteúdo em telas maiores.

### `NovaSolicitacao.jsx`
- O `<div className="grid grid-cols-2 gap-4">` do par Origem/Destino vira
  `grid grid-cols-1 sm:grid-cols-2 gap-4` — mesmo ajuste já usado em
  `AbastecimentoForm` (`resources/js`) para o mesmo tipo de problema.
- Nenhuma outra mudança no formulário (demais campos já são de coluna única).

### `MinhasSolicitacoes.jsx`
- A `<table>` passa a ser envolvida por `<div className="overflow-x-auto">`
  dentro do card branco existente, para que a rolagem fique isolada na tabela
  e não vaze para a página.

## Testes

- Sem mudança em regras de negócio/validação — não são necessários novos testes
  de backend.
- Teste manual (não há suite de teste de UI configurada no projeto):
  1. Abrir "Nova Solicitação" e "Minhas Solicitações" em viewport mobile
     (< 640px) com DevTools e confirmar que nenhum conteúdo é cortado
     horizontalmente.
  2. Confirmar que o botão hambúrguer abre o drawer da sidebar, que o overlay
     fecha ao clicar fora, e que navegar por um item do menu fecha o drawer.
  3. Confirmar que em desktop/tablet (≥ 768px) a sidebar volta a aparecer fixa,
     sem hambúrguer, como hoje.
  4. Confirmar que a tabela de "Minhas Solicitações" rola horizontalmente
     dentro do próprio card em telas estreitas, sem mover o restante da página.
