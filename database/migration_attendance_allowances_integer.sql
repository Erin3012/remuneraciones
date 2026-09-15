-- Convierte los montos diarios de asistencia a pesos enteros.
ALTER TABLE attendance_records
  MODIFY COLUMN lunch_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
  MODIFY COLUMN snack_amount DECIMAL(14,0) NOT NULL DEFAULT 0;
