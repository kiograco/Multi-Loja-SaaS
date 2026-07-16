-- OrderHub schema. Idempotent: safe to run repeatedly (migrate:up).

-- ---------------------------------------------------------------------------
-- Accounts and tenants
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            UUID PRIMARY KEY,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tenants (
    id             UUID PRIMARY KEY,
    owner_user_id  UUID NOT NULL REFERENCES users(id),
    store_name     TEXT NOT NULL,
    webhook_url    TEXT NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_tenants_owner ON tenants(owner_user_id);

-- ---------------------------------------------------------------------------
-- Products (CRUD aggregate, tenant-scoped)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id             UUID PRIMARY KEY,
    tenant_id      UUID NOT NULL,
    name           TEXT NOT NULL,
    price_cents    BIGINT NOT NULL CHECK (price_cents >= 0),
    currency       TEXT NOT NULL DEFAULT 'BRL',
    stock_quantity INTEGER NOT NULL CHECK (stock_quantity >= 0),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_products_tenant ON products(tenant_id);

-- ---------------------------------------------------------------------------
-- Event store (append-only, source of truth for the Order aggregate)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_store (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    aggregate_id UUID NOT NULL,
    tenant_id    UUID NOT NULL,
    event_type   TEXT NOT NULL,
    payload      JSONB NOT NULL,
    occurred_at  TIMESTAMPTZ NOT NULL,
    version      INTEGER NOT NULL,
    CONSTRAINT uq_event_store_aggregate_version UNIQUE (aggregate_id, version)
);
CREATE INDEX IF NOT EXISTS idx_event_store_aggregate ON event_store(aggregate_id);
CREATE INDEX IF NOT EXISTS idx_event_store_tenant ON event_store(tenant_id);

-- ---------------------------------------------------------------------------
-- Read models / projections (derived, disposable, rebuildable)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_summary_projection (
    order_id       UUID PRIMARY KEY,
    tenant_id      UUID NOT NULL,
    customer_name  TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    status         TEXT NOT NULL,
    total_cents    BIGINT NOT NULL,
    currency       TEXT NOT NULL,
    items          JSONB NOT NULL,
    tracking_code  TEXT NULL,
    created_at     TIMESTAMPTZ NOT NULL,
    updated_at     TIMESTAMPTZ NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_order_summary_tenant_status ON order_summary_projection(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_order_summary_tenant_created ON order_summary_projection(tenant_id, created_at);

CREATE TABLE IF NOT EXISTS daily_sales_projection (
    tenant_id    UUID NOT NULL,
    sales_date   DATE NOT NULL,
    orders_count INTEGER NOT NULL DEFAULT 0,
    revenue_cents BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (tenant_id, sales_date)
);

CREATE TABLE IF NOT EXISTS top_products_projection (
    tenant_id     UUID NOT NULL,
    product_id    UUID NOT NULL,
    product_name  TEXT NOT NULL,
    units_sold    BIGINT NOT NULL DEFAULT 0,
    revenue_cents BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (tenant_id, product_id)
);
CREATE INDEX IF NOT EXISTS idx_top_products_tenant_units ON top_products_projection(tenant_id, units_sold DESC);

-- ---------------------------------------------------------------------------
-- Idempotency ledger for async jobs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS processed_jobs (
    job_id       TEXT PRIMARY KEY,
    job_type     TEXT NOT NULL,
    processed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
