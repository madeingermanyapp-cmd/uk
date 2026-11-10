<?php
declare(strict_types=1);
return [
"CREATE TABLE IF NOT EXISTS manor_page_drafts (page_id CHAR(26) PRIMARY KEY, data JSON NOT NULL, approved_hash CHAR(64) NULL, approved_at DATETIME NULL, approved_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
];
