CREATE TABLE IF NOT EXISTS company_loans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  principal BIGINT UNSIGNED NOT NULL,
  installment_amount BIGINT UNSIGNED NOT NULL,
  installment_count SMALLINT UNSIGNED NOT NULL,
  start_period CHAR(7) NOT NULL,
  status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_company_loans_employee_status (company_id,employee_id,status),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_loan_installments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  installment_number SMALLINT UNSIGNED NOT NULL,
  due_period CHAR(7) NOT NULL,
  amount BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  payroll_period_id BIGINT UNSIGNED NULL,
  paid_at TIMESTAMP NULL,
  UNIQUE KEY uq_company_loan_installment (loan_id,installment_number),
  KEY idx_company_loan_due (employee_id,due_period,status),
  FOREIGN KEY (loan_id) REFERENCES company_loans(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
