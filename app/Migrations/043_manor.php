<?php
declare(strict_types=1);
// Additive Wonham extension; existing VoxelBooking records remain untouched.
return [
"CREATE TABLE IF NOT EXISTS manor_config (id INT PRIMARY KEY, data JSON NOT NULL) ENGINE=InnoDB",
"INSERT IGNORE INTO manor_config VALUES (1, JSON_OBJECT())",
"CREATE TABLE IF NOT EXISTS manor_units (id CHAR(26) PRIMARY KEY, tenant_id CHAR(26) NOT NULL, parent_id CHAR(26) NULL, resource_id CHAR(26) NULL, name VARCHAR(255) NOT NULL, mode VARCHAR(30) NOT NULL, data JSON NOT NULL, enabled TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_requests (id CHAR(26) PRIMARY KEY, customer_id CHAR(26) NOT NULL, unit_id CHAR(26) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, status VARCHAR(40) NOT NULL DEFAULT 'draft', starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, guests INT NOT NULL, details TEXT NOT NULL, consent_text TEXT NOT NULL, consent_at DATETIME NOT NULL, hold_until DATETIME NULL, accepted_quote_id CHAR(26) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY manor_request_interval(starts_at,ends_at)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_allocations (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, resource_id CHAR(26) NOT NULL, booking_id CHAR(26) NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, expires_at DATETIME NULL, KEY manor_resource_interval(resource_id,starts_at,ends_at), UNIQUE KEY manor_request_resource(request_id,resource_id)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_documents (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, type VARCHAR(80) NOT NULL, storage_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_quotes (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, version INT NOT NULL, data JSON NOT NULL, expires_at DATETIME NOT NULL, accepted_data JSON NULL, accepted_at DATETIME NULL, UNIQUE KEY manor_quote_version(request_id,version)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_payments (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, kind VARCHAR(20) NOT NULL, amount_pence BIGINT NOT NULL, reference VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, verified_at DATETIME NULL, UNIQUE KEY manor_payment_reference(request_id,kind,reference)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_history (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NULL, actor VARCHAR(100) NOT NULL, action VARCHAR(80) NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_messages (id CHAR(26) PRIMARY KEY, kind VARCHAR(20) NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
];
