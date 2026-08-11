-- Ejecutar una sola vez en la base de datos existente.
CREATE TABLE IF NOT EXISTS global_parameter_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL,
  values_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_global_parameters_period (period),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO global_parameter_versions(period, values_json, created_by)
SELECT period, MAX(values_json), MAX(created_by)
FROM parameter_versions
GROUP BY period;

UPDATE parameter_versions pv
JOIN global_parameter_versions gp ON gp.period = pv.period
SET pv.values_json = gp.values_json,
    pv.created_by = gp.created_by;
