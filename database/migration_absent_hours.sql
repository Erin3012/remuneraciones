ALTER TABLE payroll_variables
  ADD COLUMN absent_hours DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unpaid_leave_days;
