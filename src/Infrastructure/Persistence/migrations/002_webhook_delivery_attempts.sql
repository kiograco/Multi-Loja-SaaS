-- Queryable history of DispatchWebhookJob attempts (success/failure), so the
-- store owner has visibility beyond the server log. Idempotent, like 001.
CREATE TABLE IF NOT EXISTS webhook_delivery_attempts (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tenant_id     UUID NOT NULL,
    order_id      UUID NULL,
    url           TEXT NOT NULL,
    success       BOOLEAN NOT NULL,
    response_code INTEGER NULL,
    error         TEXT NULL,
    attempted_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_webhook_attempts_tenant ON webhook_delivery_attempts(tenant_id, attempted_at DESC);
