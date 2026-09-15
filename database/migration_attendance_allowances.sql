ALTER TABLE attendance_records
  ADD COLUMN lunch_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER check_out,
  ADD COLUMN snack_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER lunch_amount;
