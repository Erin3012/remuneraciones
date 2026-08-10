-- Ejecutar una sola vez en la base de datos existente.
ALTER TABLE users
  MODIFY company_id BIGINT UNSIGNED NULL,
  ADD COLUMN global_role ENUM('none','admin') NOT NULL DEFAULT 'none' AFTER role;

CREATE TABLE user_companies (
  user_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, company_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO user_companies(user_id, company_id, role, active)
SELECT id, company_id, role, active FROM users WHERE company_id IS NOT NULL;

UPDATE users SET global_role='admin' WHERE role='admin';
