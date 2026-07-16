# Especificação Técnica — Frontend Web do OrderHub (PHP + Twig)

> **Instrução para o agente de IA:** este documento complementa `SPEC-projeto-saas-pedidos.md` e assume que as Fases 0 a 4 daquele documento (domínio, event store, multi-tenancy, CQRS e read models) já estão implementadas. Não duplique lógica de negócio aqui — a camada Web **reaproveita** os mesmos Command Handlers e Query Handlers já usados pela API REST. Se algo aqui conflitar com o documento principal, o documento principal prevalece.

---

## 1. Objetivo

Adicionar uma interface Web server-side rendered (SSR) ao OrderHub, permitindo que o dono da loja gerencie produtos e pedidos e visualize o dashboard **sem** depender de um frontend JavaScript separado. O objetivo arquitetural principal é demonstrar que a arquitetura hexagonal já construída suporta múltiplos canais de entrada/saída (API JSON e Web HTML) **sem duplicar regra de negócio** — só a camada `Interface` muda.

---

## 2. Decisão de Stack

| Item | Escolha | Justificativa |
|---|---|---|
| Motor de templates | **Twig 3** | padrão de fato no ecossistema PHP moderno, auto-escaping por padrão (segurança contra XSS), herança de templates |
| Interatividade sem SPA | **HTMX** (via CDN, sem build step) | permite trocar fragmentos de HTML sem recarregar a página inteira, sem precisar de Node.js/Webpack/Vite |
| CSS | CSS puro, sem framework pesado (ou Pico.css/Tailwind via CDN, opcional) | manter o projeto sem etapa de build; foco continua sendo o backend PHP |
| Autenticação Web | **Sessão PHP** (`session_start()`, cookie httpOnly) — diferente do JWT usado na API | interface web tradicional não deve expor token no localStorage; sessão é mais apropriada e segura para este canal |
| Assets estáticos | Servidos direto de `/public/assets` | sem pipeline de build; JS mínimo necessário |

**Não usar** React/Vue/build step neste projeto — isso é intencional. O ponto do exercício é provar competência em PHP server-side, não em frontend JS. Se quiser demonstrar SPA no futuro, isso deve ser um projeto separado no portfólio.

---

## 3. Como a Camada Web se Encaixa na Arquitetura Hexagonal

```
Requisição HTTP (HTML) → Web\Controller
    → reutiliza o MESMO CommandBus/QueryBus da API
    → CommandHandler / QueryHandler (Application, já existentes)
    → Controller recebe DTO/resultado
    → renderiza Twig template com esse dado
    → retorna HTML
```

**Regra inegociável:** nenhum `Web\Controller` pode acessar `Domain` ou `Infrastructure` diretamente. Ele só fala com `Application` (Commands/Queries), exatamente como o `Api\Controller` já faz. Isso é o que prova, na prática, que a arquitetura está desacoplada da apresentação.

---

## 4. Páginas e Rotas Web

Prefixo `/app` (para diferenciar claramente de `/api/v1`).

| Rota | Método | Descrição | Query/Command reaproveitado |
|---|---|---|---|
| `/app/login` | GET/POST | formulário de login, cria sessão | `AuthenticateUserCommand` |
| `/app/logout` | POST | destrói sessão | — |
| `/app/dashboard` | GET | métricas do tenant | `GetDashboardSummaryQuery` |
| `/app/products` | GET | lista de produtos | `ListProductsQuery` |
| `/app/products/new` | GET/POST | formulário de criação | `CreateProductCommand` |
| `/app/products/{id}/edit` | GET/POST | formulário de edição | `UpdateProductCommand` |
| `/app/orders` | GET | lista de pedidos, filtrável por status (query string `?status=`) | `ListOrdersQuery` |
| `/app/orders/{id}` | GET | detalhe do pedido, com timeline de eventos e botões de ação | `GetOrderSummaryQuery` |
| `/app/orders/{id}/pay` | POST | aciona pagamento (fragmento HTMX de retorno) | `PayOrderCommand` |
| `/app/orders/{id}/ship` | POST | aciona envio (fragmento HTMX de retorno) | `ShipOrderCommand` |
| `/app/orders/{id}/cancel` | POST | cancela pedido (fragmento HTMX de retorno) | `CancelOrderCommand` |

**Detalhe importante da página `/app/orders/{id}`:** ela deve renderizar a **timeline de eventos do pedido** (`OrderCreated`, `PaymentReceived`, `OrderShipped`, ...) lida diretamente do Event Store via uma query dedicada (`GetOrderEventTimelineQuery`, nova, simples: lista os eventos brutos de um agregado em ordem cronológica). Essa página é a melhor vitrine visual do Event Sourcing implementado — mostre a natureza imutável e cronológica dos eventos na interface.

---

## 5. Autenticação Web (diferente da API)

- Login via formulário (`email` + `senha`) → valida credenciais → cria sessão PHP contendo `user_id` e `tenant_id`.
- Middleware `RequireWebAuthMiddleware` protege todas as rotas `/app/*` exceto `/app/login`.
- Cookie de sessão: `httpOnly`, `secure` (em produção), `sameSite=Lax`.
- **Não reaproveitar JWT aqui.** São dois mecanismos de autenticação propositalmente diferentes — documente essa decisão no README (mostra maturidade: "canal diferente, mecanismo de auth apropriado ao canal").

---

## 6. Uso do HTMX (interatividade sem SPA)

Padrão a seguir em toda ação que muda estado (pagar, enviar, cancelar pedido):

```html
<button
  hx-post="/app/orders/{{ order.id }}/pay"
  hx-target="#order-status-panel"
  hx-swap="outerHTML"
  hx-confirm="Confirmar pagamento deste pedido?">
  Marcar como pago
</button>

<div id="order-status-panel">
  {% include 'orders/_status_panel.html.twig' %}
</div>
```

O Controller, ao receber uma requisição HTMX (identificável pelo header `HX-Request: true`), retorna **apenas o fragmento Twig** (`_status_panel.html.twig`), não a página inteira. Caso a requisição não seja HTMX (ex: acesso direto à URL), o Controller deve redirecionar de volta para a página do pedido (fallback progressive enhancement).

---

## 7. Estrutura de Pastas (adição à estrutura já existente)

```
orderhub/
├── src/
│   ├── Interface/
│   │   ├── Api/                       (já existe)
│   │   └── Web/
│   │       ├── Controller/
│   │       │   ├── AuthController.php
│   │       │   ├── DashboardController.php
│   │       │   ├── ProductController.php
│   │       │   └── OrderController.php
│   │       └── Middleware/
│   │           └── RequireWebAuthMiddleware.php
│   └── Application/
│       └── Query/
│           └── GetOrderEventTimeline/     (nova query, simples)
├── templates/
│   ├── layout.html.twig
│   ├── partials/
│   │   ├── _nav.html.twig
│   │   └── _flash_messages.html.twig
│   ├── auth/
│   │   └── login.html.twig
│   ├── dashboard/
│   │   └── index.html.twig
│   ├── products/
│   │   ├── list.html.twig
│   │   └── form.html.twig
│   └── orders/
│       ├── list.html.twig
│       ├── detail.html.twig
│       ├── _status_panel.html.twig     (fragmento HTMX)
│       └── _event_timeline.html.twig   (fragmento HTMX)
├── public/
│   ├── index.php                       (front controller, já existente para a API)
│   └── assets/
│       ├── css/
│       │   └── app.css
│       └── js/
│           └── (vazio — HTMX vem via CDN)
```

---

## 8. Exemplo de Controller (para orientar o padrão a seguir)

```php
<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Query\GetOrderSummary\GetOrderSummaryQuery;
use OrderHub\Application\Query\GetOrderEventTimeline\GetOrderEventTimelineQuery;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use Twig\Environment;

final class OrderController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
        private readonly Environment $twig,
    ) {
    }

    public function detail(string $orderId, string $tenantId): string
    {
        $order = $this->queryBus->ask(new GetOrderSummaryQuery($orderId, $tenantId));
        $timeline = $this->queryBus->ask(new GetOrderEventTimelineQuery($orderId, $tenantId));

        return $this->twig->render('orders/detail.html.twig', [
            'order' => $order,
            'timeline' => $timeline,
        ]);
    }

    public function pay(string $orderId, string $tenantId, bool $isHtmxRequest): string
    {
        $this->commandBus->dispatch(new PayOrderCommand($orderId, $tenantId));

        $order = $this->queryBus->ask(new GetOrderSummaryQuery($orderId, $tenantId));

        if ($isHtmxRequest) {
            return $this->twig->render('orders/_status_panel.html.twig', ['order' => $order]);
        }

        // fallback sem JS: redireciona de volta
        return $this->twig->render('orders/detail.html.twig', ['order' => $order]);
    }
}
```

Esse exemplo existe para deixar claro: **o Controller Web não contém regra de negócio**, só orquestra Query/Command + renderização.

---

## 9. Roadmap de Implementação (Fases)

Numeradas continuando o documento principal, para deixar claro que dependem dele.

### Fase 10 — Infraestrutura Twig e Layout Base
- Adicionar `twig/twig` ao `composer.json`.
- Configurar `Twig\Environment` no container de DI, com cache de templates habilitado (exceto em ambiente `dev`).
- Criar `layout.html.twig` com blocos `{% block content %}`, navegação e área de mensagens flash.
- **Critério de aceite:** uma rota de teste (`/app/ping`) renderiza um template Twig que estende o layout corretamente.

### Fase 11 — Autenticação Web (Sessão)
- Implementar `AuthController` (login/logout) e `RequireWebAuthMiddleware`.
- Sessão armazenando `user_id` e `tenant_id`.
- **Critério de aceite:** teste de integração garantindo que acessar `/app/dashboard` sem sessão redireciona para `/app/login`.

### Fase 12 — Dashboard Web
- `DashboardController` reaproveitando `GetDashboardSummaryQuery`.
- Template com os mesmos dados do endpoint `/api/v1/dashboard/summary`, mas em HTML (tabelas/gráficos simples com CSS, sem lib JS de gráfico pesada — pode usar SVG simples gerado no PHP ou uma lib leve via CDN).
- **Critério de aceite:** dashboard web e endpoint API retornam os mesmos números para o mesmo tenant (teste comparando os dois).

### Fase 13 — CRUD de Produtos via Web
- `ProductController` com listagem, criação e edição via formulários HTML tradicionais (POST com redirect, sem exigir HTMX aqui).
- Validação de formulário com mensagens de erro renderizadas no template (reaproveitando as mesmas exceptions de domínio da API).
- **Critério de aceite:** criar/editar produto via formulário web reflete corretamente na listagem da API.

### Fase 14 — Gestão de Pedidos via Web + HTMX
- `OrderController` com listagem, detalhe, e as três ações (pagar/enviar/cancelar) via HTMX conforme Seção 6.
- Implementar `GetOrderEventTimelineQuery` e o template `_event_timeline.html.twig` mostrando a timeline crua de eventos do Event Store.
- **Critério de aceite:** disparar uma ação via HTMX atualiza o painel de status sem reload de página (verificável manualmente); a timeline reflete exatamente os eventos gravados no event store, na ordem correta.

### Fase 15 — Polimento e Acessibilidade
- Revisar uso de `<label>`, contraste de cores, navegação por teclado nos formulários.
- Mensagens flash de sucesso/erro após cada ação.
- **Critério de aceite:** nenhuma ação destrutiva (cancelar pedido) ocorre sem confirmação (`hx-confirm` ou `confirm()` no fallback).

---

## 10. Testes

- **Testes de integração** para cada Controller Web, usando um client HTTP de teste (ex: Symfony `BrowserKit` ou `Psr\Http\Message` test double), verificando:
  - Status HTTP correto.
  - Redirecionamento quando não autenticado.
  - Conteúdo esperado no HTML renderizado (buscar por texto/atributos-chave, não snapshot completo da página).
- **Não é necessário** testar HTMX/JS no PHPUnit — isso é comportamento de browser. Se quiser cobrir isso, use um teste manual documentado no README ou, opcionalmente, Playwright/Cypress como projeto à parte (fora do escopo deste documento).

---

## 11. Atualização Esperada no README

Adicionar ao README principal do OrderHub:
1. Seção "Interface Web" explicando a URL (`/app`), credenciais de teste (usuário seed) e prints/GIF da tela do dashboard e da timeline de eventos.
2. Explicação da decisão de usar sessão (Web) vs JWT (API) para autenticação.
3. Explicação de por que Twig + HTMX foi escolhido em vez de uma SPA — reforçar que é uma escolha deliberada para manter o projeto 100% PHP sem pipeline de build.

---

## 12. Definição de Pronto (Definition of Done) do Frontend

- [ ] Todas as rotas da Seção 4 implementadas e funcionais.
- [ ] Login/logout funcionando via sessão, protegendo todas as rotas `/app/*`.
- [ ] Dashboard web e API retornam os mesmos dados (mesma fonte de verdade).
- [ ] Ações de pedido (pagar/enviar/cancelar) funcionam via HTMX, com fallback sem JS.
- [ ] Timeline de eventos do pedido visível e correta na página de detalhe.
- [ ] Nenhum Controller Web contém lógica de negócio — só orquestração de Query/Command + render.
- [ ] Testes de integração cobrindo os principais fluxos de cada Controller.
- [ ] README atualizado conforme Seção 11.

---

**Fim do documento.** Este documento deve ser lido em conjunto com `SPEC-projeto-saas-pedidos.md`; qualquer ambiguidade deve ser resolvida priorizando a simplicidade e a reutilização da camada `Application` já existente.
