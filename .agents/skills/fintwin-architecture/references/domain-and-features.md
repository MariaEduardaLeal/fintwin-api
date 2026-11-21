# Funcionalidades e Modelo de Domínio (`FinTwin`)

## 1. Visão de Produto: O que o FinTwin faz

O FinTwin atua como um **Gêmeo Digital Financeiro** que espelha a vida financeira real do usuário (contas, extratos, cartões, faturas, parcelas futuras, carteira de investimentos e proventos) para simular decisões antes que aconteçam:

1. **Consolidação Open Finance (Pluggy):**
   sincronização periódica e assíncrona em fila Redis de saldos bancários, transações categorizadas e faturas de cartão de crédito.
2. **Mapeamento de Renda Futura Comprometida (Data Unrolling):**
   em vez de enxergar apenas o total da fatura atual, cada compra parcelada é desdobrada mês a mês nas faturas futuras. Isso revela exatamente quando a renda comprometida vai aliviar (*Burn-down* de parcelas).
3. **Simulador "E Se?" (Motor de Projeção):**
   o usuário simula uma nova compra (à vista ou em *N* parcelas), alteração de gasto recorrente ou aporte, e o motor cruza saldo líquido + histórico de fluxo de caixa + parcelas futuras + proventos esperados (com cache em Redis invalidado em novas movimentações).
4. **Conselheiro AI (DeepSeek):**
   avalia desejos de compra em linguagem natural usando o estado real das dívidas mapeadas, reserva de emergência e nível de rigidez configurado no perfil (`ai_strictness_level`), retornando recomendação estruturada e justificativa financeira.
5. **Portfólio e Proventos (Brapi):**
   atualiza cotações de ações, FIIs, ETFs, BDRs e renda fixa, além de projetar dividendos/JCP/rendimentos futuros no calendário (`dividends_schedule`).

---

## 2. Os 5 Domínios de Banco de Dados (MySQL 8.0)

### 2.1 Domínio Users
- `users` (`App\Models\User`): autenticação Sanctum (`HasApiTokens`).
- `user_financial_profiles` (`App\Models\UserFinancialProfile`): `monthly_income`, `monthly_fixed_expenses`, `emergency_fund_target`, `risk_profile` (`RiskProfile`), `investment_horizon_months`, `ai_strictness_level`, `financial_goals` (JSON).
- **Repository:** `UserRepositoryInterface` -> `EloquentUserRepository`.

### 2.2 Domínio Open Finance (Pluggy)
- `bank_accounts` (`App\Models\BankAccount`): contas vinculadas (`pluggy_item_id`, `pluggy_account_id`, `type`: `BankAccountType`, `balance`, `last_synced_at`).
- `categories` (`App\Models\Category`): categorias globais (`user_id = null`, populadas por `CategorySeeder` com 11 categorias padrão) ou personalizadas por usuário, incluindo flag `is_essential` para diferenciar gastos essenciais de discricionários.
- `transactions` (`App\Models\Transaction`): extratos bancários com `amount`, `type` (`TransactionType`), `status` (`TransactionStatus`), `transaction_date` e índices compostos em `(user_id, transaction_date)` e `(bank_account_id, transaction_date)`.
- **Repository:** `TransactionRepositoryInterface` -> `EloquentTransactionRepository` (calcula saldo líquido `getTotalLiquidBalance` e fluxo de caixa mensal `getMonthlyCashFlowSummary`).

### 2.3 Domínio de Crédito e Parcelas
- `credit_cards` (`App\Models\CreditCard`): limite total/disponível, dia de fechamento (`closing_day`) e vencimento (`due_day`).
- `invoices` (`App\Models\Invoice`): faturas por cartão e `reference_month` (`YYYY-MM`), com `status` (`InvoiceStatus`: `open`, `closed`, `paid`, `overdue`, `future`).
- `credit_transactions` (`App\Models\CreditTransaction`): armazena cada parcela individualmente (`installment_group_id`, `installment_number`, `total_installments`, `purchase_date`, `billing_date`, `is_projected`) com unicidade por `(credit_card_id, pluggy_transaction_id, installment_number)` e índice `(user_id, billing_date)`.
- **Repository:** `CreditCardRepositoryInterface` -> `EloquentCreditCardRepository` (`getMonthlyCommittedInstallments` soma parcelas comprometidas por `YYYY-MM`).

### 2.4 Domínio de Portfólio
- `assets` (`App\Models\Asset`): catálogo único por `ticker` (`AssetClass`: `stock`, `fii`, `fixed_income`, `etf`, `bdr`, `crypto`), `current_price` e `price_updated_at`.
- `portfolios` (`App\Models\Portfolio`): carteiras do usuário com `is_primary` e `target_allocation` (JSON).
- `portfolio_assets` (`App\Models\PortfolioAsset`): posição consolidada (`quantity`, `average_price`, `invested_amount`).
- **Repository:** `PortfolioRepositoryInterface` -> `EloquentPortfolioRepository`.

### 2.5 Domínio de Proventos
- `dividends_schedule` (`App\Models\DividendSchedule`): agenda de proventos por ativo (`DividendType`: `dividend`, `jcp`, `yield`, `amortization`; `DividendStatus`: `scheduled`, `paid`, `estimated`), `amount_per_share`, `ex_date` e `payment_date`.
- **Repository:** `DividendScheduleRepositoryInterface` -> `EloquentDividendScheduleRepository` (`getUpcomingDividendsForUser` multiplica `quantity * amount_per_share` para projetar receita passiva futura).

---

## 3. Fluxo Planejado das Próximas Fases

- **Fase 3 (Ingestão Redis / Horizon):**
  - `SyncPluggyBankTransactionsJob`: busca contas e transações na Pluggy, categoriza e atualiza saldos.
  - `SyncPluggyCreditInvoicesJob`: sincroniza cartões/faturas e executa o *unrolling* de compras parceladas em `credit_transactions` futuras.
  - `SyncAssetPricesJob` e `SyncFutureDividendsJob`: consomem `BrapiClient` para atualizar `assets` e `dividends_schedule`.
- **Fase 4 (Core do Gêmeo Digital):**
  - `FutureDebtCommitmentService`: consulta `CreditCardRepositoryInterface::getMonthlyCommittedInstallments` para medir o peso mensal das parcelas sobre a renda.
  - `FinancialSimulationService`: recebe `SimulationRequestDto`, combina saldo líquido + fluxo mensal + compromissos de cartão + proventos futuros + cenário simulado e armazena resultado em cache Redis (`Cache::remember`).
- **Fase 5 (Conselheiro AI):**
  - `AiPurchaseDecisionService`: monta o contexto financeiro resumido (sem vazar segredos ou dados desnecessários) e envia ao `DeepSeekClient` com System Prompt focado em saúde financeira.
- **Fases 6–9 (`fintwin-mobile` — React Native CLI):**
  - Consome `https://fintwin.freedevs.tech/api/v1` via Axios com token Bearer do Sanctum, NativeWind (Dark Mode), Reanimated + Victory Native/Skia (60fps), Pluggy Connect WebView, Dashboard "E Se?", Chatbot AI e Timeline de alívio de parcelas.
