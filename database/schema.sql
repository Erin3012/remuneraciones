CREATE TABLE companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  rut VARCHAR(20) NOT NULL,
  giro VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_rut (rut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
    global_role ENUM('none','admin') NOT NULL DEFAULT 'none',
    active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_email (email),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  rut VARCHAR(20) NOT NULL,
  full_name VARCHAR(180) NOT NULL,
    hire_date DATE NULL,
    termination_date DATE NULL,
  position VARCHAR(150) NULL,
  base_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
  contract_type ENUM('Indefinido','Plazo Fijo','Obra o Faena') NOT NULL DEFAULT 'Indefinido',
  gratification_type ENUM('Art.50','Garantizada') NOT NULL DEFAULT 'Art.50',
  health_institution ENUM('Fonasa','Isapre') NOT NULL DEFAULT 'Fonasa',
  isapre_plan_uf DECIMAL(10,4) NOT NULL DEFAULT 0,
  afp VARCHAR(80) NOT NULL DEFAULT '',
  meal_allowance DECIMAL(14,2) NOT NULL DEFAULT 0,
  transport_allowance DECIMAL(14,2) NOT NULL DEFAULT 0,
  family_loads INT NOT NULL DEFAULT 0,
  status ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  ccaf VARCHAR(100) NULL,
  commune VARCHAR(100) NULL,
  region VARCHAR(120) NULL,
  mutual_rate DECIMAL(8,5) NULL,
  contributes_afp TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employee_company_rut (company_id,rut),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payroll_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  status ENUM('draft','review','closed') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  UNIQUE KEY uq_period_company (company_id,period),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parameter_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  values_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parameters_period (company_id,period),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payroll_variables (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  medical_leave_days INT NOT NULL DEFAULT 0,
  unpaid_leave_days INT NOT NULL DEFAULT 0,
  overtime_50 DECIMAL(10,2) NOT NULL DEFAULT 0,
  overtime_100 DECIMAL(10,2) NOT NULL DEFAULT 0,
  taxable_bonus DECIMAL(14,2) NOT NULL DEFAULT 0,
  commissions DECIMAL(14,2) NOT NULL DEFAULT 0,
  guaranteed_gratification DECIMAL(14,2) NOT NULL DEFAULT 0,
  non_taxable_bonus DECIMAL(14,2) NOT NULL DEFAULT 0,
  advance DECIMAL(14,2) NOT NULL DEFAULT 0,
  company_loan DECIMAL(14,2) NOT NULL DEFAULT 0,
  ccaf_loan DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_discounts DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_variable_employee_period (period_id,employee_id),
  FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payslips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  calculation_json JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payslip_employee_period (period_id,employee_id),
  FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
