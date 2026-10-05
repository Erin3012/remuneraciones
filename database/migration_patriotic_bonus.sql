ALTER TABLE payroll_variables
  ADD COLUMN IF NOT EXISTS patriotic_bonus DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER guaranteed_gratification,
  ADD COLUMN IF NOT EXISTS patriotic_bonus_discount DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER ccaf_loan;
