-- Order history table used by the "BSF Shop" plugin.
-- Seeded with demo data on first start (takes ~30-60 seconds).

CREATE TABLE IF NOT EXISTS wp_bsf_orders (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_email VARCHAR(190)    NOT NULL,
    product_sku    VARCHAR(40)     NOT NULL,
    total          DECIMAL(10,2)   NOT NULL,
    status         VARCHAR(20)     NOT NULL,
    notes          VARCHAR(255)    NOT NULL DEFAULT '',
    created_at     DATETIME        NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET SESSION max_recursive_iterations = 3000000;

INSERT INTO wp_bsf_orders (customer_email, product_sku, total, status, notes, created_at)
WITH RECURSIVE seq (n) AS (
    SELECT 1
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 2000000
)
SELECT
    CONCAT('customer', (n * 7919) % 50000, '@example.com'),
    CONCAT('SKU-', LPAD(n % 300, 4, '0')),
    ROUND(5 + (n % 9500) / 10, 2),
    ELT(1 + (n % 4), 'completed', 'processing', 'refunded', 'failed'),
    REPEAT('x', 120),
    DATE_SUB('2026-10-01 00:00:00', INTERVAL (n % 730) DAY)
FROM seq;
