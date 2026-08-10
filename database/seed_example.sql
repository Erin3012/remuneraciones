INSERT INTO companies(name,rut,giro) VALUES ('MI EMPRESA PYME SpA','76.543.210-K','Comercio al por menor');
SET @company_id=LAST_INSERT_ID();
INSERT INTO employees(company_id,rut,full_name,hire_date,position,base_salary,contract_type,gratification_type,health_institution,isapre_plan_uf,afp,meal_allowance,transport_allowance,family_loads,ccaf,commune,region,mutual_rate,contributes_afp) VALUES
(@company_id,'12.345.678-9','María Fernanda González Rojas','2021-03-15','Vendedora',850000,'Indefinido','Art.50','Fonasa',0,'Modelo',50000,40000,1,'Los Andes','Santiago','Metropolitana de Santiago',.009,1),
(@company_id,'9.876.543-2','Carlos Eduardo Muñoz Soto','2024-06-01','Bodeguero',520000,'Plazo Fijo','Art.50','Isapre',2.8,'Uno',30000,25000,2,'18 de Septiembre','Valparaíso','Valparaíso',.0149,1),
(@company_id,'15.432.100-K','Ana Patricia Torres Vidal','2019-08-01','Jefa de Ventas',2800000,'Indefinido','Art.50','Isapre',8.5,'Cuprum',60000,50000,0,'La Araucana','Las Condes','Metropolitana de Santiago',.009,1),
(@company_id,'11.111.222-3','Luis Alberto Pérez Campos','2022-11-10','Técnico Mantención',780000,'Indefinido','Art.50','Fonasa',0,'Habitat',45000,35000,3,'Los Héroes','Maipú','Metropolitana de Santiago',.0149,1),
(@company_id,'16.789.012-3','Javiera Belén Soto Ramírez','2025-02-01','Recepcionista',450000,'Obra o Faena','Garantizada','Fonasa',0,'PlanVital',0,15000,0,'Los Andes','Concepción','Biobío',.009,1);
INSERT INTO payroll_periods(company_id,period) VALUES(@company_id,'2026-06');
SET @period_id=LAST_INSERT_ID();
INSERT INTO parameter_versions(company_id,period,values_json) VALUES(@company_id,'2026-06','{"uf":34500,"utm":66714,"minimum_wage":500000,"afp_cap_uf":81.6,"unemployment_cap_uf":122.4,"health_cap":166320,"mutual":0.009,"sis":0.0153,"sc_worker":0.006,"sc_employer_indefinite":0.024,"sc_employer_fixed":0.03,"sanna":0.0003,"reform_afp":0.001,"reform_ssp":0.009,"family_allowance":15000,"afp_rates":{"Modelo":0.1177,"Uno":0.1144,"Cuprum":0.1144,"Habitat":0.1147,"PlanVital":0.115},"tax_brackets":[[0,941638,0,0],[941639,2092530,0.04,37666],[2092531,3487550,0.08,121367],[3487551,4882570,0.135,313182],[4882571,6277590,0.23,777026],[6277591,8370120,0.304,1241568],[8370121,21622810,0.35,1626593],[21622811,999999999,0.4,2707733]]}');
INSERT INTO payroll_variables(period_id,employee_id) SELECT @period_id,id FROM employees WHERE company_id=@company_id;
