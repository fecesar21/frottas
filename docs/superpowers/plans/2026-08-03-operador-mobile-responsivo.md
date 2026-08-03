# Operador Mobile Responsivo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix action buttons being clipped inside operator form modals (Checkin/Viagem/Abastecimento) on mobile, and add a bottom nav for the `operador` profile.

**Architecture:** `Modal.jsx` becomes full-screen below the `sm` breakpoint (640px) via Tailwind responsive classes; a new `FormActionBar` component gives every operator form a sticky, always-visible action row; a new `BottomNav` component (mobile-only, operador-only) replaces the hamburger for that profile, sharing its route-permission logic with `Sidebar.jsx` through a new shared hook. No backend, validation, or business-logic changes.

**Tech Stack:** React 18, Tailwind CSS, react-router-dom, lucide-react icons. No frontend test runner is configured in this repo (`package.json` has no test script) — verification is `npm run build` (must succeed with no errors) plus manual responsive checks in browser DevTools, as specified per task.

## Global Constraints

- Touch targets (inputs, buttons, nav items) must be at least 48px tall/wide — from spec section "Touch targets e tipografia".
- No changes to form validation, mutation logic, or API calls — from spec section "Escopo" (fora do escopo: mudanças de regras de negócio).
- `Modal.jsx` public API (`open`, `onClose`, `title`, `children`, `size`) must stay unchanged — from spec section "Modal.jsx".
- Above `sm` (640px), Modal and form appearance must remain visually identical to current behavior — from spec section "Testes", check 2.
- `BottomNav` must only ever appear for `perfil === 'operador'` and only below `md` — from spec section "BottomNav (novo)".

---

### Task 1: Extract shared operator route-permission hook

**Files:**
- Create: `resources/js/hooks/useOperadorRotasPermitidas.js`
- Modify: `resources/js/components/layout/Sidebar.jsx:48-51`

**Interfaces:**
- Consumes: `useAuth()` from `resources/js/contexts/AuthContext` — needs `isOperador` (bool) and `checkinAtivo` (object|null), both already used in `Sidebar.jsx`.
- Produces: `useOperadorRotasPermitidas(): string[] | null` — a hook that returns `null` when the current user is not an operador (no restriction applies), or an array of allowed route paths (e.g. `['/checkins']` or `['/checkins', '/viagens', '/abastecimentos']`) when they are. Task 7 (`BottomNav`) consumes this exact return shape.

- [ ] **Step 1: Create the hook**

```js
// resources/js/hooks/useOperadorRotasPermitidas.js
import { useAuth } from '../contexts/AuthContext'

const ROTAS_SEM_CHECKIN = ['/checkins']
const ROTAS_COM_CHECKIN = ['/checkins', '/viagens', '/abastecimentos']

export function useOperadorRotasPermitidas() {
  const { isOperador, checkinAtivo } = useAuth()
  if (!isOperador) return null
  return checkinAtivo ? ROTAS_COM_CHECKIN : ROTAS_SEM_CHECKIN
}
```

- [ ] **Step 2: Use the hook in `Sidebar.jsx`**

Replace the inline computation at `resources/js/components/layout/Sidebar.jsx:48-51`:

```js
  // Rotas visíveis para operadores dependem do estado do checkin
  const rotasPermitidas = isOperador
    ? (checkinAtivo ? ['/checkins', '/viagens', '/abastecimentos'] : ['/checkins'])
    : null
```

with:

```js
  const rotasPermitidas = useOperadorRotasPermitidas()
```

Add the import at the top of `Sidebar.jsx`:

```js
import { useOperadorRotasPermitidas } from '../../hooks/useOperadorRotasPermitidas'
```

Remove the now-unused `checkinAtivo` from the `useAuth()` destructure on line 45 if nothing else in the file uses it (check with a search first — `Sidebar.jsx` also reads `isOperador`, `isAdmin`, `isGestor`, `user`, `logout`, keep those).

- [ ] **Step 3: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual check**

Run `composer dev` (or `npm run dev` if already running the backend separately), log in as an operador account, and confirm the Sidebar still shows exactly the same items as before (1 item without an active checkin, 3 items with one).

- [ ] **Step 5: Commit**

```bash
git add resources/js/hooks/useOperadorRotasPermitidas.js resources/js/components/layout/Sidebar.jsx
git commit -m "refactor: extract shared operador route-permission hook"
```

---

### Task 2: Create `FormActionBar` component

**Files:**
- Create: `resources/js/components/ui/FormActionBar.jsx`

**Interfaces:**
- Consumes: nothing beyond `children` (React nodes — the action buttons).
- Produces: `FormActionBar` — default export, React component, props `{ children }`. Tasks 4, 5, 6 import and render this instead of the current `<div className="flex justify-end">`.

- [ ] **Step 1: Create the component**

```jsx
// resources/js/components/ui/FormActionBar.jsx
export default function FormActionBar({ children }) {
  return (
    <div className="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 -mx-6 -mb-5 flex justify-end gap-3">
      {children}
    </div>
  )
}
```

Note: the forms this wraps live inside `Modal.jsx`'s content div (`px-6 py-5`, see Task 3) — the negative margins (`-mx-6 -mb-5`) let the bar span the full modal width and sit flush with the bottom edge, undoing the parent's padding just for this bar. This matches how the modal's own header (`px-6 py-4`) spans full width above the scrollable content.

- [ ] **Step 2: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors (component isn't wired in yet, this just checks for syntax errors).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ui/FormActionBar.jsx
git commit -m "feat: add FormActionBar component for sticky form action rows"
```

---

### Task 3: Make `Modal.jsx` full-screen on mobile

**Files:**
- Modify: `resources/js/components/ui/Modal.jsx`

**Interfaces:**
- Consumes: nothing new — same props as before (`open`, `onClose`, `title`, `children`, `size`).
- Produces: same public API; visual behavior changes below `sm` (640px) only. Tasks 4-6 rely on the modal's content area still being `flex flex-col` with a scrollable middle section and now expect a `FormActionBar` to sit correctly at its bottom via `sticky`.

- [ ] **Step 1: Update the modal markup**

Replace the full return block in `resources/js/components/ui/Modal.jsx` (currently lines 16-32):

```jsx
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center sm:p-4 animate-fade-in">
      <div className="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" onClick={onClose} />
      <div
        className={`relative bg-white shadow-2xl w-full flex flex-col animate-fade-in
          h-[100dvh] sm:h-auto sm:rounded-2xl sm:w-full sm:${sizeMap[size]} sm:max-h-[90vh]`}
      >
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white sm:rounded-t-2xl shrink-0">
          <h2 className="text-base font-semibold text-gray-800">{title}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-1.5 rounded-lg transition-all duration-150 min-w-[48px] min-h-[48px] flex items-center justify-center"
          >
            <X size={18} />
          </button>
        </div>
        <div className="overflow-y-auto flex-1 px-6 py-5">{children}</div>
      </div>
    </div>
  )
```

Note: Tailwind cannot interpolate dynamic breakpoints from a template literal for arbitrary values like `sm:${sizeMap[size]}` — `sizeMap[size]` (e.g. `'max-w-lg'`) must be composed into a literal class string per size. Since Tailwind's JIT scans for whole class names, replace the `sizeMap` and className construction with:

```jsx
  const sizeMap = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center sm:p-4 animate-fade-in">
      <div className="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" onClick={onClose} />
      <div
        className={`relative bg-white shadow-2xl w-full flex flex-col animate-fade-in
          h-[100dvh] sm:h-auto sm:rounded-2xl sm:max-h-[90vh] ${sizeMap[size]}`}
      >
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white sm:rounded-t-2xl shrink-0">
          <h2 className="text-base font-semibold text-gray-800">{title}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-1.5 rounded-lg transition-all duration-150 min-w-[48px] min-h-[48px] flex items-center justify-center"
          >
            <X size={18} />
          </button>
        </div>
        <div className="overflow-y-auto flex-1 px-6 py-5">{children}</div>
      </div>
    </div>
  )
```

- [ ] **Step 2: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 3: Manual check**

Run `npm run dev` (or `composer dev`), open any existing modal (e.g. "Nova viagem" from the Viagens list) in a desktop-width browser window — confirm it still looks centered with rounded corners, same as before. Then use DevTools device toolbar to switch to a mobile width (e.g. 375px) and confirm the modal now fills the entire screen with no rounded corners.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ui/Modal.jsx
git commit -m "feat: make Modal full-screen below sm breakpoint"
```

---

### Task 4: Update `ViagemForm` — touch targets and `FormActionBar`

**Files:**
- Modify: `resources/js/pages/viagens/ViagemForm.jsx`

**Interfaces:**
- Consumes: `FormActionBar` from Task 2 (`resources/js/components/ui/FormActionBar.jsx`).
- Produces: no new exports; same `ViagemForm({ onSuccess })` component signature.

- [ ] **Step 1: Add the import**

At the top of `resources/js/pages/viagens/ViagemForm.jsx`, add:

```js
import FormActionBar from '../../components/ui/FormActionBar'
```

- [ ] **Step 2: Bump input/select padding to `py-3`**

In `resources/js/pages/viagens/ViagemForm.jsx`, every `className` string containing `px-3 py-2` on an `input` or `select` element (lines 78, 84, 93, 101, 115, 122 in the current file) becomes `px-3 py-3`. Example (origem field, currently line 77-78):

```jsx
          <input type="text" required value={form.origem} onChange={e => setForm(f => ({ ...f, origem: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
```

Apply the same `py-2` → `py-3` change to: destino input, motivo_viagem select, numero_atendimento input, km_saida input.

- [ ] **Step 3: Replace the submit row with `FormActionBar`**

Replace the current bottom block (lines 126-130):

```jsx
      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Criando...' : 'Iniciar viagem'}
        </button>
      </div>
```

with:

```jsx
      <FormActionBar>
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 min-h-[48px] rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Criando...' : 'Iniciar viagem'}
        </button>
      </FormActionBar>
```

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 5: Manual check**

Open "Nova viagem" modal at a mobile width (375px) in DevTools, scroll the form content, confirm "Iniciar viagem" stays pinned at the bottom of the screen at all scroll positions.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/viagens/ViagemForm.jsx
git commit -m "fix: sticky action bar and larger touch targets in ViagemForm"
```

---

### Task 5: Update `CheckinForm` — touch targets and `FormActionBar`

**Files:**
- Modify: `resources/js/pages/checkins/CheckinForm.jsx`

**Interfaces:**
- Consumes: `FormActionBar` from Task 2.
- Produces: no new exports; same `CheckinForm({ onSuccess })` component signature.

- [ ] **Step 1: Add the import**

At the top of `resources/js/pages/checkins/CheckinForm.jsx`, add:

```js
import FormActionBar from '../../components/ui/FormActionBar'
```

- [ ] **Step 2: Bump input/select padding to `py-3`**

Every `px-3 py-2` on an `input`/`select` in `resources/js/pages/checkins/CheckinForm.jsx` (turno select at line 70, km_saida input at line 79, nivel_combustivel_saida input at line 86) becomes `px-3 py-3`. Example (turno select, currently lines 69-70):

```jsx
        <select value={form.turno} onChange={e => setForm(f => ({ ...f, turno: e.target.value }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
```

- [ ] **Step 3: Replace the submit row with `FormActionBar`**

Replace the current bottom block (lines 89-93):

```jsx
      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar check-in'}
        </button>
      </div>
```

with:

```jsx
      <FormActionBar>
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 min-h-[48px] rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar check-in'}
        </button>
      </FormActionBar>
```

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 5: Manual check**

Open "Registrar check-in" modal at a mobile width (375px) in DevTools, confirm "Registrar check-in" stays pinned at the bottom while scrolling the fields above it.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/checkins/CheckinForm.jsx
git commit -m "fix: sticky action bar and larger touch targets in CheckinForm"
```

---

### Task 6: Update `AbastecimentoForm` — responsive grid, touch targets, `FormActionBar`

**Files:**
- Modify: `resources/js/pages/abastecimentos/AbastecimentoForm.jsx`

**Interfaces:**
- Consumes: `FormActionBar` from Task 2.
- Produces: no new exports; same `AbastecimentoForm({ onSuccess })` component signature.

- [ ] **Step 1: Add the import**

At the top of `resources/js/pages/abastecimentos/AbastecimentoForm.jsx`, add:

```js
import FormActionBar from '../../components/ui/FormActionBar'
```

- [ ] **Step 2: Make the grid responsive**

Line 59 currently reads:

```jsx
      <div className="grid grid-cols-2 gap-4">
```

Change to:

```jsx
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
```

Every child `<div className="col-span-2">` (motorista, veiculo, and the total estimado block) must become `<div className="col-span-1 sm:col-span-2">` so it still spans both columns once the grid becomes 2-column at `sm`, but doesn't force a phantom second column at 1-column width. There are 3 such divs (lines 60, 72, 108 in the current file).

- [ ] **Step 3: Bump input/select padding to `py-3`**

Every `px-3 py-2` on an `input`/`select` in this file (posto, combustivel, litros, valor_litro, km_momento, nota_fiscal — lines 87, 92, 99, 105, 115, 121) becomes `px-3 py-3`.

- [ ] **Step 4: Replace the submit row with `FormActionBar`**

Replace the current bottom block (lines 125-129):

```jsx
      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar abastecimento'}
        </button>
      </div>
```

with:

```jsx
      <FormActionBar>
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 min-h-[48px] rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar abastecimento'}
        </button>
      </FormActionBar>
```

- [ ] **Step 5: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 6: Manual check**

Open "Registrar abastecimento" (the longest of the 3 forms) at 375px width in DevTools: confirm fields stack in a single column, "Registrar abastecimento" stays pinned at the bottom while scrolling, and at ≥640px width the grid returns to 2 columns matching current production layout.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/abastecimentos/AbastecimentoForm.jsx
git commit -m "fix: responsive grid, sticky action bar, larger touch targets in AbastecimentoForm"
```

---

### Task 7: Create `BottomNav` component

**Files:**
- Create: `resources/js/components/layout/BottomNav.jsx`

**Interfaces:**
- Consumes: `useOperadorRotasPermitidas()` from Task 1 (returns `string[] | null`); `useAuth()` for `isOperador`; the same `sections` route metadata already defined inline in `Sidebar.jsx` (icon + label per path) — duplicate the small subset needed (`/checkins`, `/viagens`, `/abastecimentos`) rather than importing `Sidebar.jsx`'s internal `sections` array, since that array isn't exported and exporting it would couple the two components' internals together.
- Produces: `BottomNav` — default export, React component, no props (reads auth/route state itself). Task 8 renders `<BottomNav />` inside `Layout.jsx`.

- [ ] **Step 1: Create the component**

```jsx
// resources/js/components/layout/BottomNav.jsx
import { NavLink } from 'react-router-dom'
import { LogIn, Route, Fuel } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import { useOperadorRotasPermitidas } from '../../hooks/useOperadorRotasPermitidas'

const ITEMS = [
  { to: '/checkins', label: 'Check-in', icon: LogIn },
  { to: '/viagens', label: 'Viagens', icon: Route },
  { to: '/abastecimentos', label: 'Abastecer', icon: Fuel },
]

export default function BottomNav() {
  const { isOperador } = useAuth()
  const rotasPermitidas = useOperadorRotasPermitidas()

  if (!isOperador || !rotasPermitidas) return null

  const itensVisiveis = ITEMS.filter(item => rotasPermitidas.includes(item.to))
  if (itensVisiveis.length === 0) return null

  return (
    <nav
      className="fixed bottom-0 inset-x-0 z-30 md:hidden bg-navy-950 border-t border-white/10 flex"
      style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
    >
      {itensVisiveis.map(({ to, label, icon: Icon }) => (
        <NavLink
          key={to}
          to={to}
          className={({ isActive }) =>
            `flex-1 flex flex-col items-center justify-center gap-1 min-h-[48px] py-2 text-xs font-medium
            ${isActive ? 'text-brand-300' : 'text-white/60'}`
          }
        >
          <Icon size={20} />
          {label}
        </NavLink>
      ))}
    </nav>
  )
}
```

- [ ] **Step 2: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors (component isn't wired into `Layout.jsx` yet, this just checks for syntax errors).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/layout/BottomNav.jsx
git commit -m "feat: add BottomNav component for operador mobile navigation"
```

---

### Task 8: Wire `BottomNav` into `Layout.jsx`

**Files:**
- Modify: `resources/js/components/layout/Layout.jsx`

**Interfaces:**
- Consumes: `BottomNav` from Task 7; `useAuth()` for `isOperador` (already available via `useAuth` import in this file — check current import list, it currently only pulls what's used, so `isOperador` needs to be added to the destructure since `Layout.jsx` doesn't yet read `useAuth()` directly — see Step 1).
- Produces: no new exports; same default export `Layout` component.

- [ ] **Step 1: Add the imports and auth read**

At the top of `resources/js/components/layout/Layout.jsx`, add:

```js
import BottomNav from './BottomNav'
```

Add `useAuth` import (the file already imports `useAuth` from `'../../contexts/AuthContext'` at line 4 for `useRastreamentoGlobal`, but the `Layout` component itself does not call it). Inside `export default function Layout()`, add a call to read `isOperador`:

```js
  const { isOperador } = useAuth()
```

Place this alongside the existing `const location = useLocation()` line near the top of the function body.

- [ ] **Step 2: Add bottom padding and render `BottomNav`**

In `resources/js/components/layout/Layout.jsx`, the `<main>` element (currently line 90):

```jsx
        <main className="flex-1 p-5 md:p-6 overflow-auto animate-fade-in">
          <Outlet />
        </main>
```

becomes:

```jsx
        <main className={`flex-1 p-5 md:p-6 overflow-auto animate-fade-in ${isOperador ? 'pb-20 md:pb-6' : ''}`}>
          <Outlet />
        </main>
        <BottomNav />
```

`<BottomNav />` is placed as a sibling right after `<main>`, inside the same `<div className="flex-1 flex flex-col min-w-0">` wrapper, so it's `fixed`-positioned relative to the viewport (per its own `fixed bottom-0 inset-x-0` styling from Task 7) regardless of where in the tree it's mounted.

- [ ] **Step 3: Verify the build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual check — full spec verification pass**

With `composer dev` running:
1. Log in as operador, view at 375px width in DevTools: confirm `BottomNav` appears at the bottom with 1 item (no active checkin) or 3 items (active checkin), the active route is highlighted, and page content doesn't scroll behind the bar.
2. Log in as gestor or admin, view at 375px width: confirm the hamburger + `Sidebar` overlay behavior is unchanged and `BottomNav` does not render.
3. Resize to ≥768px (`md`) as operador: confirm `BottomNav` disappears and the regular `Sidebar` takes over.
4. Re-open each of the 3 operator forms (Checkin, Viagem, Abastecimento) at 375px width one more time to confirm the action buttons remain reachable without conflicting with `BottomNav` (the modal is `fixed inset-0 z-50`, above `BottomNav`'s `z-30`, so it should fully cover the bottom nav while open — visually confirm this is the case).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/layout/Layout.jsx
git commit -m "feat: wire BottomNav into Layout for operador mobile navigation"
```
