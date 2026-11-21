---
name: fintwin-architecture
description: >-
  Explains how the FinTwin (Digital Financial Twin) project works, covering its
  product capabilities, polyrepo architecture, Laravel 13 Clean Flow backend,
  relational MySQL schema, integrations (Pluggy, DeepSeek, Brapi), OpenTelemetry
  observability, and production deployment. Use this skill whenever onboarding,
  answering architecture/feature questions, or implementing new phases in FinTwin.
---

# FinTwin — Guia de Arquitetura e Funcionalidades

O **FinTwin** é um **Gêmeo Digital Financeiro** (*Digital Financial Twin*) construído em arquitetura **Polyrepo**:
- **`fintwin-api`** (Backend REST em Laravel 13 + Sanctum + MySQL 8.0 + Redis + OpenTelemetry)
- **`fintwin-mobile`** (Frontend nativo em React Native CLI — planejado para as Fases 6–9)

Seu objetivo é consolidar contas bancárias, faturas e parcelas futuras de cartão de crédito, investimentos e proventos previstos para responder a duas perguntas centrais em tempo real:
1. **"E se?" (Simulação de Fluxo de Caixa):** qual o impacto real no orçamento e na reserva de emergência nos próximos meses ao assumir uma compra parcelada ou um novo aporte?
2. **"Devo comprar?" (Conselheiro AI):** julgamento contextualizado via DeepSeek considerando renda comprometida, parcelas futuras já mapeadas e metas do perfil financeiro.

---

## Status Atual de Implementação

| Fase | Escopo | Status |
| :--- | :--- | :--- |
| **Fase 1** | Infraestrutura, `.env`, Docker (Nginx, Sail, MySQL 8.0, Redis, OTel), Sanctum, `laravel-clean-flow`, Guzzle (`PluggyClient`, `DeepSeekClient`, `BrapiClient`) + Deploy HTTPS em `fintwin.freedevs.tech` | Implementado |
| **Fase 2** | Modelagem MySQL (11 migrations dos 5 domínios), 8 Enums, 12 Models, `CategorySeeder` e 5 Repositories com contratos/implementações | Implementado |
| **Fase 3** | Motor de Ingestão assíncrono (Laravel Horizon + Jobs Pluggy/Brapi/Proventos) | Planejado |
| **Fase 4** | Core do Gêmeo Digital (`SimulationRequestDto`, `FutureDebtCommitmentService`, `FinancialSimulationService` com cache Redis) | Planejado |
| **Fase 5** | Conselheiro AI (`AiPurchaseDecisionService` + System Prompt para DeepSeek) | Planejado |
| **Fases 6–9** | App mobile React Native (`fintwin-mobile`: Pluggy Connect, Dashboard "E Se?", Chatbot AI, Timeline de Renda, APK Release) | Planejado |

---

## Fluxo Arquitetural do Backend (Clean Flow)

Toda nova feature de API no `fintwin-api` deve seguir estritamente a cadeia em **PSR-4 PascalCase**:

```text
HTTP Request
  -> Controller (app/Http/Controllers/Api/V1/)
  -> Form Request (app/Http/Requests/Api/V1/)
  -> DTO (app/DTOs/)
  -> Service (app/Services/)
  -> Repository Interface -> Eloquent Repository (app/Repositories/)
  -> API Resource (app/Http/Resources/Api/V1/)
```

### Regras essenciais do projeto
- **Zero credenciais no código:** segredos (DB, Redis, `PLUGGY_*`, `DEEPSEEK_*`, `BRAPI_*`) ficam somente no `.env` e são lidos via `config/integrations.php` e `config/opentelemetry.php`.
- **Resiliência de integrações:** clientes externos em `app/Integrations/` herdam `BaseApiClient` e usam `GuzzleClientFactory` (timeout + retry automático em `429`/`5xx` e erros de conexão) lançando `IntegrationException` sem vazar tokens.
- **Desdobramento de parcelas (Unrolling):** compras parceladas no cartão são persistidas por parcela individual em `credit_transactions` (`installment_number`, `total_installments`, `billing_date`, `is_projected`) para que o cálculo de renda futura comprometida seja uma agregação direta por mês.
- **Observabilidade não-intrusiva:** `OpenTelemetryService` instrumenta HTTP (`/api/*`), queries SQL (`DB::listen`, sem valores/bindings) e jobs em fila (`Queue::before/after/failing`), com `try/catch` para nunca derrubar requisições caso o coletor esteja indisponível.

---

## Documentação Detalhada (Leitura Sob Demanda)

- **Arquitetura técnica, pastas, rotas, Guzzle, OpenTelemetry e deploy em produção:**
  leia [backend-architecture.md](./references/backend-architecture.md).
- **Funcionalidades, os 5 domínios de dados, fluxo de simulação, IA e integração com o mobile:**
  leia [domain-and-features.md](./references/domain-and-features.md).

---

## Comandos Rápidos de Desenvolvimento e Validação

```bash
# Rodar toda a suíte de testes (SQLite :memory:, OTel desabilitado)
composer test

# Verificar/corrigir formatação PSR-12 / Laravel Pint
./vendor/bin/pint --test

# Validar compose de desenvolvimento (Sail + Nginx + MySQL 8.0 + Redis + OTel + Jaeger)
docker compose config --quiet

# Validar compose de produção (VPS Hostinger)
docker compose -f docker-compose.prod.yml config --quiet
```
