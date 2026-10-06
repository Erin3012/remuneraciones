CREATE TABLE IF NOT EXISTS family_allowance_settings (
 employee_id BIGINT UNSIGNED NOT NULL,
 cycle_year SMALLINT UNSIGNED NOT NULL,
 accredited_band ENUM('A','B','C','D') NULL,
 income_basis ENUM('auto','semester','year') NOT NULL DEFAULT 'auto',
 reference VARCHAR(255) NOT NULL DEFAULT '',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(employee_id,cycle_year),
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS family_income_history (
 employee_id BIGINT UNSIGNED NOT NULL,
 period CHAR(7) NOT NULL,
 total_income DECIMAL(14,0) NOT NULL,
 income_days TINYINT UNSIGNED NOT NULL,
 reference VARCHAR(255) NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(employee_id,period),
 FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
