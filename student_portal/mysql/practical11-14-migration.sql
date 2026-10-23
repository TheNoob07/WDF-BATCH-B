USE studenthub;

ALTER TABLE events
    ADD COLUMN poster_path VARCHAR(255) NULL AFTER description;

ALTER TABLE users
    ADD COLUMN full_name VARCHAR(80) NOT NULL DEFAULT '' AFTER password_hash,
    ADD COLUMN mobile VARCHAR(20) NOT NULL DEFAULT '' AFTER full_name,
    ADD COLUMN program VARCHAR(120) NOT NULL DEFAULT '' AFTER mobile,
    ADD COLUMN year TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER program,
    ADD COLUMN gender VARCHAR(30) NOT NULL DEFAULT 'prefer_not_to_say' AFTER year;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_actor (actor_id),
    CONSTRAINT fk_audit_logs_actor
        FOREIGN KEY (actor_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
