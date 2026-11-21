# Arquitetura Backend (`fintwin-api`)

## 1. Stack e Dependências Principais (`composer.json`)
- **Framework:** Laravel 13 (`laravel/framework ^13.17`, rodando v13.42.0 em PHP 8.3).
- **Autenticação API:** `laravel/sanctum` (`HasApiTokens` habilitado em `App\Models\User`).
- **Scaffolding / Arquitetura:** `eduardaleal/laravel-clean-flow` (`make:flow`, `make:service`, `make:repository`).
- **HTTP Client:** `guzzlehttp/guzzle` (8.5.0) via fábrica compartilhada.
- **Observabilidade:** `open-telemetry/sdk`, `open-telemetry/exporter-otlp`, `open-telemetry/opentelemetry-auto-laravel`.
- **Ambiente Local:** `laravel/sail` + `docker-compose.yml` (Nginx, PHP 8.3, MySQL 8.0, Redis, OTel Collector, Jaeger).

---

## 2. Mapa de Diretórios e Camadas

| Caminho | Responsabilidade |
| :--- | :--- |
| `app/Http/Controllers/Api/V1/` | Controllers finos: recebem Request validado, montam DTO, delegam ao Service e devolvem Resource. |
| `app/Http/Requests/Api/V1/` | Validação e autorização de entrada HTTP (Form Requests). |
| `app/DTOs/` | Objetos `readonly` imutáveis que transportam dados tipados entre Controller e Service. |
| `app/Services/` | Regras de negócio puras (simulação, comprometimento de renda, decisão AI, saúde da API). |
| `app/Repositories/Contracts/` | Interfaces de acesso a dados (contratos injetados nos Services). |
| `app/Repositories/Eloquent/` | Implementações concretas usando Eloquent ORM e agregações otimizadas por índice. |
| `app/Http/Resources/Api/V1/` | Serialização JSON padronizada para o app mobile (`fintwin-mobile`). |
| `app/Enums/` | Backed string enums compartilhados entre Models, casts e Services. |
| `app/Integrations/` | Clientes Guzzle isolados por provedor (`Pluggy`, `DeepSeek`, `Brapi`) + `Support/` + `Exceptions/`. |
| `app/Observability/` | `OpenTelemetryService` para criação/exportação de spans OTLP/HTTP Protobuf. |
| `app/Http/Middleware/` | `TraceHttpRequest`, aplicado ao grupo `api` em `bootstrap/app.php`. |
| `app/Providers/` | `AppServiceProvider` (singletons HTTP/OTel + listeners DB/Queue) e `RepositoryServiceProvider` (bindings de repositórios). |

### Exemplo vivo já implementado (Health Check)
`GET /api/v1/health` (`routes/api.php`):
1. `HealthCheckController::__invoke()`
2. `HealthCheckService::check()`
3. `HealthCheckRepositoryInterface` → `HealthCheckRepository` (testa PDO/MySQL e Redis/Cache)
4. Retorna `HealthCheckDto` formatado por `HealthCheckResource` (`200` se `ok`, `503` se `degraded`).

---

## 3. Clientes HTTP Externos (`app/Integrations/`)

Todos os clientes são registrados como **singletons** em `AppServiceProvider` usando `config/integrations.php`:

- **`GuzzleClientFactory` (`app/Integrations/Support/GuzzleClientFactory.php`):**
  configura `base_uri`, `timeout`, `connect_timeout`, headers JSON e middleware `Middleware::retry` com backoff exponencial para `ConnectException`, HTTP `429` e `5xx`. Aceita `HandlerStack` customizado para testes unitários com `MockHandler`.
- **`BaseApiClient` (`app/Integrations/Support/BaseApiClient.php`):**
  centraliza execução de `request()`, decodificação JSON estrita (`JSON_THROW_ON_ERROR`) e conversão de falhas em `IntegrationException` sem expor headers/tokens sensíveis.
- **`PluggyClient` (`app/Integrations/Pluggy/PluggyClient.php`):**
  - `getApiKey()`: autentica em `POST /auth` com `PLUGGY_CLIENT_ID`/`PLUGGY_CLIENT_SECRET` e guarda o `apiKey` em cache (`PLUGGY_API_KEY_TTL`, padrão `6000s`).
  - `createConnectToken()`, `getAccounts()`, `getTransactions()`, `getCreditCardBills()`: injetam `X-API-KEY` automaticamente.
- **`DeepSeekClient` (`app/Integrations/DeepSeek/DeepSeekClient.php`):**
  - `chatCompletion(array $messages, array $options = [])`: envia `POST /chat/completions` com `Authorization: Bearer {DEEPSEEK_API_KEY}` e modelo configurável (`DEEPSEEK_MODEL=deepseek-chat`).
- **`BrapiClient` (`app/Integrations/Brapi/BrapiClient.php`):**
  - `getQuotes(array $tickers, array $params = [])` e `getQuoteWithDividends(string $ticker)`: consulta `GET /quote/{tickers}` com `Bearer {BRAPI_API_TOKEN}` quando configurado.
- **`GoogleAuthClient` (`app/Integrations/Google/GoogleAuthClient.php`):**
  - `verifyIdToken(string $idToken)`: valida o `id_token` enviado pelo app mobile contra o endpoint `tokeninfo` do Google (`GOOGLE_TOKENINFO_BASE_URL`), checando `email_verified`, `iss`, `exp` e correspondência estrita do `aud` com `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_IDS`.

---

## 3.1 Autenticação (`AuthController` + `AuthService`)
- `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/google` (rate limit `10/min`) e `POST /api/v1/auth/logout` (`auth:sanctum`).
- Novos usuários via Google ou cadastro tradicional já recebem automaticamente um `UserFinancialProfile` padrão dentro de `DB::transaction`, além de token Sanctum Bearer.

---

## 3.2 Mensageria, Laravel Horizon e Agendamento (`routes/console.php`)
- **Horizon (`config/horizon.php` + `HorizonServiceProvider`):**
  - Supervisores dedicados para `open-finance`, `market-data` e `default`, com painel `/horizon` restrito a `HORIZON_ALLOWED_EMAILS` em produção.
  - Em produção (`docker-compose.prod.yml`), `fintwin-queue` executa `php artisan horizon` e `fintwin-scheduler` executa `php artisan schedule:work`.
- **Jobs assíncronos (`app/Jobs/`):**
  - `SyncPluggyBankTransactionsJob` e `SyncPluggyCreditInvoicesJob` (fila `open-finance`, `ShouldBeUnique`) disparados por `BankSyncRequestedEvent` -> `DispatchPluggySyncJobsListener` via `POST /api/v1/sync/open-finance`.
  - `SyncAssetPricesJob` (pregão B3 dias úteis `10:15`–`18:15` + fechamento `19:00` BRT) e `SyncFutureDividendsJob` (diariamente às `07:00` BRT) na fila `market-data`.

---

## 4. Observabilidade com OpenTelemetry

Configurado em `config/opentelemetry.php` (`OTEL_ENABLED`, `OTEL_SERVICE_NAME`, `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_EXPORTER_OTLP_TIMEOUT`):
- **HTTP:** `TraceHttpRequest` abre um span `KIND_SERVER` com `http.request.method`, `url.path` e `http.response.status_code`.
- **Banco de Dados:** `DB::listen` em `AppServiceProvider` chama `OpenTelemetryService::recordDatabaseQuery()` registrando `db.system`, `db.statement` (**sem bindings/valores**) e `db.duration_ms`.
- **Filas:** `Queue::before`, `Queue::after` e `Queue::failing` abrem e encerram spans `KIND_CONSUMER` por job no Redis.
- **Modo seguro:** quando `OTEL_ENABLED=false` (ex.: `phpunit.xml` e inicialização padrão em produção), usa `NoopTracer` sem custo de rede.

---

## 5. Infraestrutura Docker e Produção (VPS Hostinger)

### Desenvolvimento Local (`docker-compose.yml`)
- `nginx` (`:80`) -> `laravel.test` (Sail PHP 8.3 na porta interna `:8000`)
- `mysql` (`mysql:8.0`, `:3306`)
- `redis` (`redis:7-alpine`, `:6379`)
- `otel-collector` (`:4317` gRPC / `:4318` HTTP) -> `jaeger` (UI em `http://localhost:16686`)

### Produção (`docker-compose.prod.yml` + `docker/production/`)
- **Host:** `/home/duda/projetos/fintwin/fintwin-api`
- **Domínio público:** `https://fintwin.freedevs.tech` (SSL Let's Encrypt gerenciado via Certbot no proxy compartilhado `infra-nginx` em `/srv/infra/nginx/conf.d/fintwin.conf`).
- **Isolamento:** os serviços `fintwin-mysql` (MySQL 8.0), `fintwin-redis` (Redis 7 com senha), `fintwin-queue` (`queue:work redis`) e `fintwin-otel-collector` rodam na rede privada `fintwin-internal`. Apenas `fintwin-api` participa da rede externa `infra-net` para responder ao `infra-nginx` na porta `8000`.
- **Reverse Proxy:** `bootstrap/app.php` habilita `$middleware->trustProxies(at: '*')` para que o Laravel reconheça corretamente `https://` e o IP real encaminhados pelo `infra-nginx`.
