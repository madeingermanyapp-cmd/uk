<?php
declare(strict_types=1);
return [
"CREATE TABLE IF NOT EXISTS manor_request_details (request_id CHAR(26) PRIMARY KEY, locale VARCHAR(5) NOT NULL DEFAULT 'en', rental_mode VARCHAR(30) NOT NULL, secret_cipher TEXT NULL, data JSON NOT NULL) ENGINE=InnoDB",
];
