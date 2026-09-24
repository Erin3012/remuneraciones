ALTER TABLE payroll_variables
  ADD COLUMN viatico DECIMAL(14,0) NOT NULL DEFAULT 0 AFTER non_taxable_bonus;
