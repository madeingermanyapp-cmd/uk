<?php
declare(strict_types=1);
return [
"CREATE TABLE IF NOT EXISTS manor_invitation_templates (id CHAR(26) PRIMARY KEY, name VARCHAR(255) NOT NULL, style VARCHAR(20) NOT NULL, data JSON NOT NULL, enabled TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_guests (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, template_id CHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NULL, token_hash CHAR(64) NOT NULL UNIQUE, qr_hash CHAR(64) NOT NULL UNIQUE, token_cipher TEXT NOT NULL, data JSON NOT NULL, rsvp VARCHAR(20) NOT NULL DEFAULT 'pending', rsvp_at DATETIME NULL, checked_at DATETIME NULL, checked_by VARCHAR(100) NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY manor_guest_request(request_id)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_pages (id CHAR(26) PRIMARY KEY, slug VARCHAR(80) NOT NULL, locale VARCHAR(5) NOT NULL, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, seo_description TEXT NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, UNIQUE KEY manor_page_locale(slug,locale)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_correspondence (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, sender VARCHAR(20) NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY manor_correspondence_request(request_id)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS manor_access_tokens (id CHAR(26) PRIMARY KEY, request_id CHAR(26) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL) ENGINE=InnoDB",
];
