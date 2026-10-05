-- Ejecutar mediante php tools/migrate_global_periods.php para capturar históricos.
CREATE TABLE IF NOT EXISTS global_parameter_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL,
  values_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_global_parameters_period (period),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS global_period_closures (
  period CHAR(7) NOT NULL PRIMARY KEY,
  closed_at TIMESTAMP NULL,
  closed_by BIGINT UNSIGNED NULL,
  snapshot_pending TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_period_snapshots (
  period_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  snapshot_json JSON NOT NULL,
  captured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recuperar parámetros de meses antiguos sin sobrescribir la fuente global.
INSERT INTO global_parameter_versions(period,values_json,created_by)
SELECT pv.period,pv.values_json,pv.created_by FROM parameter_versions pv
LEFT JOIN global_parameter_versions gp ON gp.period=pv.period
WHERE gp.id IS NULL AND pv.id=(SELECT MAX(p2.id) FROM parameter_versions p2 WHERE p2.period=pv.period);

INSERT INTO global_period_closures(period)
SELECT period FROM global_parameter_versions
ON DUPLICATE KEY UPDATE period=VALUES(period);

-- Solo la primera migración convierte cierres antiguos; reejecutar no re-cierra meses reabiertos.
SET @has_legacy_status = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payroll_periods' AND column_name='status');
SET @legacy_sql = IF(@has_legacy_status>0,
  'UPDATE global_period_closures g JOIN (SELECT period, MAX(COALESCE(closed_at,created_at)) closed_at FROM payroll_periods WHERE status="closed" GROUP BY period) old ON old.period=g.period SET g.closed_at=COALESCE(g.closed_at,old.closed_at),g.snapshot_pending=1',
  'DO 1');
PREPARE legacy_statement FROM @legacy_sql;
EXECUTE legacy_statement;
DEALLOCATE PREPARE legacy_statement;

INSERT INTO payroll_periods(company_id,period)
SELECT c.id,g.period FROM companies c CROSS JOIN global_parameter_versions g WHERE 1=1
ON DUPLICATE KEY UPDATE period=VALUES(period);

INSERT INTO parameter_versions(company_id,period,values_json,created_by)
SELECT c.id,g.period,g.values_json,g.created_by FROM companies c CROSS JOIN global_parameter_versions g WHERE 1=1
ON DUPLICATE KEY UPDATE values_json=VALUES(values_json),created_by=VALUES(created_by);
