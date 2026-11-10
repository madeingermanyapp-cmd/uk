<?php
// Fictional archive column parity only; production uses MySQL migrations.
return <<<'SQL'
CREATE TABLE manor_config (id INT, data JSON);
CREATE TABLE manor_units (id CHAR(26), tenant_id CHAR(26), parent_id CHAR(26), resource_id CHAR(26), name VARCHAR(255), mode VARCHAR(30), data JSON, enabled TINYINT);
CREATE TABLE manor_requests (id CHAR(26), customer_id CHAR(26), unit_id CHAR(26), token_hash CHAR(64), status VARCHAR(40), starts_at DATETIME, ends_at DATETIME, guests INT, details TEXT, consent_text TEXT, consent_at DATETIME, hold_until DATETIME, accepted_quote_id CHAR(26), created_at DATETIME, updated_at DATETIME);
CREATE TABLE manor_allocations (id CHAR(26), request_id CHAR(26), resource_id CHAR(26), booking_id CHAR(26), starts_at DATETIME, ends_at DATETIME, expires_at DATETIME);
CREATE TABLE manor_documents (id CHAR(26), request_id CHAR(26), type VARCHAR(80), storage_name VARCHAR(80), original_name VARCHAR(255), mime VARCHAR(80), bytes INT, created_at DATETIME);
CREATE TABLE manor_quotes (id CHAR(26), request_id CHAR(26), version INT, data JSON, expires_at DATETIME, accepted_data JSON, accepted_at DATETIME);
CREATE TABLE manor_payments (id CHAR(26), request_id CHAR(26), kind VARCHAR(20), amount_pence BIGINT, reference VARCHAR(255), status VARCHAR(20), created_at DATETIME, verified_at DATETIME);
CREATE TABLE manor_history (id CHAR(26), request_id CHAR(26), actor VARCHAR(100), action VARCHAR(80), data JSON, created_at DATETIME);
CREATE TABLE manor_messages (id CHAR(26), kind VARCHAR(20), data JSON, created_at DATETIME);
CREATE TABLE manor_invitation_templates (id CHAR(26), name VARCHAR(255), style VARCHAR(20), data JSON, enabled TINYINT);
CREATE TABLE manor_guests (id CHAR(26), request_id CHAR(26), template_id CHAR(26), name VARCHAR(255), email VARCHAR(255), token_hash CHAR(64), qr_hash CHAR(64), token_cipher TEXT, data JSON, rsvp VARCHAR(20), rsvp_at DATETIME, checked_at DATETIME, checked_by VARCHAR(100), revoked_at DATETIME, created_at DATETIME);
CREATE TABLE manor_pages (id CHAR(26), slug VARCHAR(80), locale VARCHAR(5), title VARCHAR(255), body TEXT, seo_description TEXT, enabled TINYINT);
CREATE TABLE manor_correspondence (id CHAR(26), request_id CHAR(26), sender VARCHAR(20), body TEXT, created_at DATETIME);
CREATE TABLE manor_access_tokens (id CHAR(26), request_id CHAR(26), token_hash CHAR(64), expires_at DATETIME, used_at DATETIME);
CREATE TABLE manor_secrets (name VARCHAR(80), secret_cipher TEXT);
CREATE TABLE manor_content_versions (id CHAR(26), data JSON, status VARCHAR(20), actor VARCHAR(100), approved_at DATETIME, created_at DATETIME);
CREATE TABLE manor_media (id CHAR(48), storage_name VARCHAR(100), original_name VARCHAR(255), mime VARCHAR(80), bytes BIGINT, alt TEXT, rights TEXT, status VARCHAR(20), approved_by VARCHAR(100), approved_at DATETIME, deleted_at DATETIME, created_at DATETIME);
CREATE TABLE manor_services (id CHAR(26), name VARCHAR(255), description TEXT, amount VARCHAR(30), refundable TINYINT, tax_bps INT, enabled TINYINT);
CREATE TABLE manor_operations (name VARCHAR(80), data JSON, checked_at DATETIME);
CREATE TABLE manor_request_details (request_id CHAR(26), locale VARCHAR(5), rental_mode VARCHAR(30), secret_cipher TEXT, data JSON);
CREATE TABLE manor_page_drafts (page_id CHAR(26), data JSON, approved_hash CHAR(64), approved_at DATETIME, approved_by VARCHAR(100), updated_at DATETIME);
SQL;
