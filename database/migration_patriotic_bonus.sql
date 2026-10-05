ALTER TABLE payroll_variables
  ADD COLUMN patriotic_bonus DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER guaranteed_gratification;

ALTER TABLE payroll_variables
  ADD COLUMN patriotic_bonus_discount DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER ccaf_loan;
