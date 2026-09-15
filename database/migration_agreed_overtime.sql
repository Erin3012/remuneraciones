ALTER TABLE payroll_variables
  ADD COLUMN agreed_overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER overtime_100,
  ADD COLUMN agreed_overtime_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER agreed_overtime_hours;
