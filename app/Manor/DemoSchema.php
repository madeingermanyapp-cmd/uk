<?php
// Fictional SQLite column parity; the runtime uses MySQL migration 043.
return <<<'SQL'
-- Fictional SQLite artifact column parity only; production uses MySQL migration 043.
CREATE TABLE manor_config (id INT, data JSON);
CREATE TABLE manor_units (id CHAR(26), tenant_id CHAR(26), parent_id CHAR(26), resource_id CHAR(26), name VARCHAR(255), mode VARCHAR(30), data JSON, enabled TINYINT);
CREATE TABLE manor_requests (id CHAR(26), customer_id CHAR(26), unit_id CHAR(26), token_hash CHAR(64), status VARCHAR(40), starts_at DATETIME, ends_at DATETIME, guests INT, details TEXT, consent_text TEXT, consent_at DATETIME, hold_until DATETIME, accepted_quote_id CHAR(26), created_at DATETIME, updated_at DATETIME);
CREATE TABLE manor_allocations (id CHAR(26), request_id CHAR(26), resource_id CHAR(26), booking_id CHAR(26), starts_at DATETIME, ends_at DATETIME, expires_at DATETIME);
CREATE TABLE manor_documents (id CHAR(26), request_id CHAR(26), type VARCHAR(80), storage_name VARCHAR(80), original_name VARCHAR(255), mime VARCHAR(80), bytes INT, created_at DATETIME);
CREATE TABLE manor_quotes (id CHAR(26), request_id CHAR(26), version INT, data JSON, expires_at DATETIME, accepted_data JSON, accepted_at DATETIME);
CREATE TABLE manor_payments (id CHAR(26), request_id CHAR(26), kind VARCHAR(20), amount_pence BIGINT, reference VARCHAR(255), status VARCHAR(20), created_at DATETIME, verified_at DATETIME);
CREATE TABLE manor_history (id CHAR(26), request_id CHAR(26), actor VARCHAR(100), action VARCHAR(80), data JSON, created_at DATETIME);
CREATE TABLE manor_messages (id CHAR(26), kind VARCHAR(20), data JSON, created_at DATETIME);
SQL;
