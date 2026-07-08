# Health Drive / FleetCore

API REST em Laravel 11 para gestão de frota: veículos, motoristas, escalas, viagens, abastecimento, plantão (passagem de turno) e registro de hodômetro. Backend puro (sem views Blade).

## Stack

- Laravel 11
- Laravel Sanctum (autenticação via token)
- SQLite (desenvolvimento e testes)

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Autenticação

Autenticação via Sanctum (token expira em 8h). O login (`POST /api/auth/login`) aceita `usuario` (username) **ou** `email`. O model de autenticação é `App\Models\Usuario` (tabela `usuarios`, coluna de senha `senha_hash`) — não o `User` padrão do Laravel.

## Autorização

Rotas protegidas combinam os seguintes middlewares:

- `auth:sanctum` — obrigatório em todas as rotas protegidas
- `admin` (`App\Http\Middleware\SomenteAdmin`) — obrigatório no CRUD de `usuarios`
- `escopo.unidade` (`App\Http\Middleware\EscopoUnidade`) — escopo multi-tenant por unidade

## Comandos úteis

```bash
composer dev              # sobe servidor + queue + logs + Vite em paralelo
php artisan test          # roda a suíte de testes (ou ./vendor/bin/phpunit)
./vendor/bin/pint         # formatação de código
php artisan tinker        # REPL interativo
```

## Documentação da API

A documentação OpenAPI é gerada automaticamente (Scramble) e fica disponível em `/docs/api` com o servidor rodando.

## Arquitetura

Para detalhes de arquitetura (models, UUIDs como chave primária, estrutura de rotas, banco de dados), veja [`CLAUDE.md`](./CLAUDE.md).
