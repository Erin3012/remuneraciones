ALTER TABLE attendance_records
  ADD COLUMN status ENUM('present','absent','medical_leave','permission','vacation','other') NOT NULL DEFAULT 'present' AFTER check_out,
  ADD COLUMN absence_reason VARCHAR(120) NULL AFTER status,
  ADD COLUMN is_exception TINYINT(1) NOT NULL DEFAULT 0 AFTER absence_reason;

CREATE TABLE attendance_days (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  day_type ENUM('working','non_working') NOT NULL DEFAULT 'working',
  status ENUM('open','complete','closed') NOT NULL DEFAULT 'open',
  notes VARCHAR(255) NULL,
  closed_by BIGINT UNSIGNED NULL,
  closed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance_day_company_date (company_id,work_date),
  KEY idx_attendance_days_company_date (company_id,work_date),
  CONSTRAINT fk_attendance_days_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_days_user FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
