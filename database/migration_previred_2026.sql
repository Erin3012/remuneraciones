-- Indicadores previsionales oficiales PREVIRED 2026.
-- Carga global: no depende de company_id y no modifica trabajadores ni variables.
-- Fuentes verificadas:
-- https://www.previred.com/wp-content/uploads/2026/01/Indicadores-Previsionales-Previred-Enero-2026.pdf
-- https://www.previred.com/wp-content/uploads/2026/02/Indicadores-Previsionales-Previred-Febrero-2026-2.pdf
-- https://www.previred.com/wp-content/uploads/2026/03/Indicadores-Previsionales-Previred-Marzo-2026.pdf
-- https://www.previred.com/wp-content/uploads/2026/04/Indicadores-Previsionales-Previred-Abril-2026.pdf
-- https://www.previred.com/wp-content/uploads/2026/05/Indicadores-Previsionales-Previred-Mayo-2026.pdf
-- https://www.previred.com/wp-content/uploads/2026/06/Indicadores-Previsionales-Previred-Junio-2026v2.pdf
-- https://www.previred.com/wp-content/uploads/2026/07/Indicadores-Previsionales-Previred-Julio-2026.pdf
-- https://www.previred.com/wp-content/uploads/2026/08/Indicadores-Previsionales-Previred-Agosto-2026-1.pdf

CREATE TABLE IF NOT EXISTS global_parameter_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL,
  values_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_global_parameters_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO global_parameter_versions (period, values_json, created_by)
VALUES
('2026-01', '{"uf":39706.07,"utm":69751,"uta":837012,"minimum_wage":539000,"minimum_wage_under18_over65":402082,"minimum_wage_non_remunerational":347434,"afp_cap_uf":89.9,"unemployment_cap_uf":135.1,"health_cap":166320,"sis":0.0154,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.031,"health_fonasa_ccaf":0.039,"apv_monthly_cap":1985304,"apv_annual_cap":23823642,"family_brackets":[[631976,22007],[923067,13505],[1439668,4267]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-02', '{"uf":39790.63,"utm":69611,"uta":835332,"minimum_wage":539000,"minimum_wage_under18_over65":402082,"minimum_wage_non_remunerational":347434,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.0154,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":1989532,"apv_annual_cap":23874378,"family_brackets":[[631976,22007],[923067,13505],[1439668,4267]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-03', '{"uf":39841.72,"utm":69889,"uta":838668,"minimum_wage":539000,"minimum_wage_under18_over65":402082,"minimum_wage_non_remunerational":347434,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.0154,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":1992086,"apv_annual_cap":23905032,"family_brackets":[[631976,22007],[923067,13505],[1439668,4267]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-04', '{"uf":40120.20,"utm":69889,"uta":838668,"minimum_wage":539000,"minimum_wage_under18_over65":402082,"minimum_wage_non_remunerational":347434,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.0154,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":2006010,"apv_annual_cap":24072120,"family_brackets":[[631976,22007],[923067,13505],[1439668,4267]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-05', '{"uf":40610.69,"utm":70588,"uta":847056,"minimum_wage":539000,"minimum_wage_under18_over65":402082,"minimum_wage_non_remunerational":347434,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.0162,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":2030535,"apv_annual_cap":24366414,"family_brackets":[[631976,22007],[923067,13505],[1439668,4267]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-06', '{"uf":40820.31,"utm":71506,"uta":858072,"minimum_wage":553553,"minimum_wage_under18_over65":412938,"minimum_wage_non_remunerational":356815,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.0162,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":2041016,"apv_annual_cap":24492186,"family_brackets":[[649039,22601],[947990,13870],[1478539,4382]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-07', '{"uf":40844.79,"utm":71649,"uta":859788,"minimum_wage":553553,"minimum_wage_under18_over65":412938,"minimum_wage_non_remunerational":356815,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.02,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":2042240,"apv_annual_cap":24506874,"family_brackets":[[649039,22601],[947990,13870],[1478539,4382]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL),
('2026-08', '{"uf":40873.77,"utm":71649,"uta":859788,"minimum_wage":553553,"minimum_wage_under18_over65":412938,"minimum_wage_non_remunerational":356815,"afp_cap_uf":90,"unemployment_cap_uf":135.2,"health_cap":166320,"sis":0.02,"mutual":0.009,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.009,"reform_ssp":0.005,"health_ccaf":0.042,"health_fonasa_ccaf":0.028,"apv_monthly_cap":2043689,"apv_annual_cap":24524262,"family_brackets":[[649039,22601],[947990,13870],[1478539,4382]],"afp_rates":{"Capital":0.1144,"Cuprum":0.1144,"Habitat":0.1127,"PlanVital":0.1116,"Provida":0.1145,"Modelo":0.1058,"Uno":0.1046}}',NULL)
ON DUPLICATE KEY UPDATE values_json=VALUES(values_json);

-- Los PDF de indicadores no publican la tabla mensual IUSC. Se conserva la
-- tabla existente y, para períodos nuevos, se deja la tabla vigente de 2026.
UPDATE global_parameter_versions
SET values_json = JSON_SET(
  values_json,
  '$.tax_brackets',
  JSON_ARRAY(
    JSON_ARRAY(0,941638,0,0),
    JSON_ARRAY(941639,2092530,0.04,37666),
    JSON_ARRAY(2092531,3487550,0.08,121367),
    JSON_ARRAY(3487551,4882570,0.135,313182),
    JSON_ARRAY(4882571,6277590,0.23,777026),
    JSON_ARRAY(6277591,8370120,0.304,1241568),
    JSON_ARRAY(8370121,21622810,0.35,1626593),
    JSON_ARRAY(21622811,999999999,0.4,2707733)
  )
)
WHERE period BETWEEN '2026-01' AND '2026-08';
