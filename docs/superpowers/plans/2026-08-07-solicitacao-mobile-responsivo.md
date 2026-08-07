# Responsividade mobile do app de solicitação Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Eliminar o corte de conteúdo em smartphone no app de solicitação de transporte (`resources/solicitacao-js`), replicando o padrão de sidebar responsiva já usado no app de motoristas (`resources/js`).

**Architecture:** A sidebar fixa (`w-60 shrink-0` sempre visível) do `Layout.jsx` vira um drawer off-canvas controlado por estado local, com overlay e botão hambúrguer, escondendo-se acima de `md:`. `NovaSolicitacao.jsx` ganha grid responsivo no par Origem/Destino e `MinhasSolicitacoes.jsx` ganha rolagem horizontal isolada na tabela.

**Tech Stack:** React + React Router + Tailwind CSS, Vite (`npm run dev`, entry `solicitacao.html`, porta 5173, proxy `/api` → `localhost:8000`).

## Global Constraints

- Nenhuma mudança em lógica de negócio, validação, autenticação ou nomes de rotas/API.
- Sem componentes novos compartilhados entre `resources/js` e `resources/solicitacao-js` — os dois apps continuam independentes.
- Não há suite de teste de UI configurada no projeto — verificação é manual via `npm run dev` + DevTools (viewport < 640px e ≥ 768px).
- Seguir o padrão visual/CSS já existente no app (paleta `navy-950`/`navy-800`, `brand-*`, classes Tailwind utilitárias inline, sem CSS-in-JS).

---

### Task 1: Sidebar responsiva em `Layout.jsx`

**Files:**
- Modify: `resources/solicitacao-js/components/Layout.jsx`

**Interfaces:**
- Consumes: `useAuth()` de `resources/solicitacao-js/contexts/AuthContext.jsx` (já usado, sem mudança de assinatura), `NavLink`/`Navigate`/`Outlet`/`useNavigate` de `react-router-dom`.
- Produces: `Layout({ children })` continua com a mesma assinatura pública (usado por `NovaSolicitacao.jsx` e `MinhasSolicitacoes.jsx` como `<Layout>{...}</Layout>`) — nenhuma prop nova é exigida dos consumidores.

- [x] **Step 1: Reescrever `Layout.jsx` com sidebar em drawer + barra mobile**

Substituir o conteúdo do arquivo por:

```jsx
import { useState } from 'react'
import { NavLink, Navigate, Outlet, useNavigate } from 'react-router-dom'
import { Truck, ClipboardPlus, ClipboardList, LogOut, Menu, X } from 'lucide-react'
import { useAuth } from '../contexts/AuthContext'

export function PrivateRoute() {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  return <Outlet />
}

const items = [
  { to: '/', label: 'Nova Solicitação', icon: ClipboardPlus },
  { to: '/minhas-solicitacoes', label: 'Minhas Solicitações', icon: ClipboardList },
]

export default function Layout({ children }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="min-h-screen bg-gray-50 md:flex">
      {open && (
        <div
          className="fixed inset-0 z-30 bg-navy-950/70 backdrop-blur-sm md:hidden"
          onClick={() => setOpen(false)}
        />
      )}

      <aside
        className={`
          fixed top-0 left-0 z-40 h-screen w-60 flex flex-col
          bg-gradient-to-b from-navy-950 to-navy-800
          transform transition-transform duration-300 ease-in-out
          md:sticky md:top-0 md:translate-x-0 md:z-auto md:shrink-0
          ${open ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        <div className="px-5 py-5 border-b border-white/10">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="bg-brand-500/20 border border-brand-400/30 rounded-xl p-2">
                <Truck size={20} className="text-brand-300" />
              </div>
              <div>
                <span className="font-bold text-base text-white tracking-tight">Health Drive</span>
                <p className="text-[10px] text-brand-400 font-medium uppercase tracking-widest">Solicitar Transporte</p>
              </div>
            </div>
            <button
              onClick={() => setOpen(false)}
              className="md:hidden text-white/50 hover:text-white p-1 rounded-lg hover:bg-white/10"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        <nav className="flex-1 py-4 px-3 space-y-0.5">
          {items.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                ${isActive
                  ? 'bg-brand-500/15 text-brand-300 border-l-2 border-brand-400 pl-[10px]'
                  : 'text-white/60 hover:bg-white/10 hover:text-white/90 border-l-2 border-transparent'
                }`
              }
            >
              <Icon size={16} className="shrink-0" />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="px-3 py-4 border-t border-white/10">
          <p className="text-sm font-medium text-white/90 truncate px-2 mb-2">{user?.nome}</p>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-white/50 hover:text-red-400 hover:bg-red-500/10 transition-all duration-150"
          >
            <LogOut size={15} />
            Sair
          </button>
        </div>
      </aside>

      <div className="flex-1 min-w-0 flex flex-col">
        <div className="md:hidden flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-100 sticky top-0 z-20">
          <button
            onClick={() => setOpen(true)}
            className="text-gray-500 hover:text-gray-800 p-1.5 rounded-lg hover:bg-gray-100"
          >
            <Menu size={20} />
          </button>
          <span className="font-semibold text-sm text-gray-800">Health Drive</span>
        </div>

        <main className="flex-1 p-6 md:p-8 max-w-3xl mx-auto w-full">{children}</main>
      </div>
    </div>
  )
}
```

Mudanças-chave em relação ao original: `min-h-screen bg-gray-50 flex` → `min-h-screen bg-gray-50 md:flex` (evita `flex` empurrar a sidebar fixa em mobile); `<aside>` ganha `fixed`/`transform`/`md:sticky` e o botão `X`; overlay novo; `<main>` agora está dentro de um wrapper `flex-1 min-w-0 flex flex-col` que também contém a barra mobile com o botão `Menu`.

- [x] **Step 2: Verificar visualmente em mobile**

Rodar `npm run dev` (a partir da raiz do projeto) e abrir `http://localhost:5173/solicitacao.html` no navegador com o DevTools em modo responsivo, largura < 640px (ex: 375px).

Esperado:
- Sidebar não aparece por padrão; aparece uma barra superior com ícone de menu (hambúrguer) e "Health Drive".
- Clicar no ícone de menu abre a sidebar deslizando da esquerda, com overlay escurecido atrás.
- Clicar no overlay ou no `X` fecha a sidebar.
- Clicar em um item do menu navega e fecha a sidebar automaticamente.
- Nenhum conteúdo horizontal cortado ou com scroll lateral indesejado na página.

- [x] **Step 3: Verificar visualmente em desktop**

No mesmo DevTools, mudar a largura para ≥ 768px (ex: 1024px).

Esperado: sidebar aparece fixa à esquerda como antes (sem hambúrguer, sem overlay), igual ao comportamento anterior à mudança.

- [x] **Step 4: Commit**

```bash
git add resources/solicitacao-js/components/Layout.jsx
git commit -m "fix: sidebar responsiva (drawer mobile) no app de solicitação"
```

---

### Task 2: Grid responsivo em `NovaSolicitacao.jsx`

**Files:**
- Modify: `resources/solicitacao-js/pages/NovaSolicitacao.jsx:101`

**Interfaces:**
- Consumes: nenhuma interface nova — só troca de classe CSS na `<div>` que envolve os campos Origem/Destino.
- Produces: nenhuma interface nova.

- [x] **Step 1: Trocar a classe do grid Origem/Destino**

Em `resources/solicitacao-js/pages/NovaSolicitacao.jsx:101`, trocar:

```jsx
          <div className="grid grid-cols-2 gap-4">
```

por:

```jsx
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
```

- [x] **Step 2: Verificar visualmente**

Com `npm run dev` rodando, abrir `http://localhost:5173/solicitacao.html`, fazer login como solicitante e selecionar o motivo "Transferência de Paciente" ou "Transporte de Colaborador(es)" (os únicos que exibem os campos Origem/Destino).

Em viewport < 640px: os selects Origem e Destino aparecem empilhados (uma coluna). Em viewport ≥ 640px: aparecem lado a lado (duas colunas), como antes.

- [x] **Step 3: Commit**

```bash
git add resources/solicitacao-js/pages/NovaSolicitacao.jsx
git commit -m "fix: grid responsivo em Origem/Destino na Nova Solicitação"
```

---

### Task 3: Rolagem horizontal isolada em `MinhasSolicitacoes.jsx`

**Files:**
- Modify: `resources/solicitacao-js/pages/MinhasSolicitacoes.jsx:37-72`

**Interfaces:**
- Consumes: nenhuma interface nova — apenas envolve a `<table>` existente em um wrapper `<div>`.
- Produces: nenhuma interface nova.

- [x] **Step 1: Envolver a tabela em um wrapper com `overflow-x-auto`**

Em `resources/solicitacao-js/pages/MinhasSolicitacoes.jsx`, dentro do card (linha 37), trocar:

```jsx
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {loading ? (
          <p className="p-6 text-sm text-gray-500">Carregando...</p>
        ) : solicitacoes.length === 0 ? (
          <p className="p-6 text-sm text-gray-500">Nenhuma solicitação encontrada.</p>
        ) : (
          <table className="w-full text-sm">
```

por:

```jsx
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {loading ? (
          <p className="p-6 text-sm text-gray-500">Carregando...</p>
        ) : solicitacoes.length === 0 ? (
          <p className="p-6 text-sm text-gray-500">Nenhuma solicitação encontrada.</p>
        ) : (
          <div className="overflow-x-auto">
          <table className="w-full text-sm">
```

E, ao final da tabela (linha 70-72), trocar:

```jsx
          </table>
        )}
      </div>
```

por:

```jsx
          </table>
          </div>
        )}
      </div>
```

- [x] **Step 2: Verificar visualmente**

Com `npm run dev` rodando, abrir "Minhas Solicitações" em viewport < 640px com pelo menos uma solicitação cadastrada (criar uma pela tela "Nova Solicitação" se a lista estiver vazia).

Esperado: a tabela rola horizontalmente dentro do próprio card (arrastando o dedo/mouse sobre ela), sem que a página inteira role lateralmente nem que o cabeçalho/menu se desloque.

- [x] **Step 3: Commit**

```bash
git add resources/solicitacao-js/pages/MinhasSolicitacoes.jsx
git commit -m "fix: rolagem horizontal isolada na tabela de Minhas Solicitações"
```
