-- Script para exportar todos los datos relacionados con una escuela específica
-- Uso: mysql -u usuario -p base_datos < export_school_data.sql > school_data_export.sql
-- Reemplaza @SCHOOL_ID por el ID de la escuela que quieres exportar

SET @SCHOOL_ID = 1; -- CAMBIAR ESTE VALOR POR EL ID DE LA ESCUELA A EXPORTAR

-- Configuración para generar INSERT statements
SET SESSION sql_mode = '';
SET foreign_key_checks = 0;

-- ===========================================
-- DATOS PRINCIPALES DE LA ESCUELA
-- ===========================================

-- 1. Escuela principal
SELECT CONCAT(
  'INSERT INTO schools (',
  'id, name, description, contact_email, contact_phone, contact_telephone, ',
  'contact_address, contact_cp, contact_city, contact_province, contact_country, ',
  'fiscal_name, fiscal_id, fiscal_address, fiscal_cp, fiscal_city, fiscal_province, fiscal_country, ',
  'iban, logo, slug, cancellation_insurance_percent, payrexx_instance, payrexx_key, ',
  'conditions_url, bookings_comission_cash, bookings_comission_boukii_pay, bookings_comission_other, ',
  'school_rate, has_ski, has_snowboard, has_telemark, has_rando, inscription, type, ',
  'active, is_test_school, has_microgate_integration, whatsapp_config, webhook_url, ',
  'settings, feature_flags, feature_flags_updated_at, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(name), ', ', QUOTE(description), ', ', QUOTE(contact_email), ', ', 
  QUOTE(contact_phone), ', ', QUOTE(contact_telephone), ', ', QUOTE(contact_address), ', ', 
  QUOTE(contact_cp), ', ', QUOTE(contact_city), ', ', QUOTE(contact_province), ', ', 
  QUOTE(contact_country), ', ', QUOTE(fiscal_name), ', ', QUOTE(fiscal_id), ', ', 
  QUOTE(fiscal_address), ', ', QUOTE(fiscal_cp), ', ', QUOTE(fiscal_city), ', ', 
  QUOTE(fiscal_province), ', ', QUOTE(fiscal_country), ', ', QUOTE(iban), ', ', 
  QUOTE(logo), ', ', QUOTE(slug), ', ', QUOTE(cancellation_insurance_percent), ', ', 
  QUOTE(payrexx_instance), ', ', QUOTE(payrexx_key), ', ', QUOTE(conditions_url), ', ', 
  QUOTE(bookings_comission_cash), ', ', QUOTE(bookings_comission_boukii_pay), ', ', 
  QUOTE(bookings_comission_other), ', ', QUOTE(school_rate), ', ', QUOTE(has_ski), ', ', 
  QUOTE(has_snowboard), ', ', QUOTE(has_telemark), ', ', QUOTE(has_rando), ', ', 
  QUOTE(inscription), ', ', QUOTE(type), ', ', QUOTE(active), ', ', QUOTE(is_test_school), ', ', 
  QUOTE(has_microgate_integration), ', ', QUOTE(whatsapp_config), ', ', QUOTE(webhook_url), ', ', 
  QUOTE(settings), ', ', QUOTE(feature_flags), ', ', QUOTE(feature_flags_updated_at), ', ', 
  QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM schools WHERE id = @SCHOOL_ID;

-- ===========================================
-- TABLAS RELACIONADAS CON SCHOOL_ID
-- ===========================================

-- 2. Usuarios asociados a la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO school_users (school_id, user_id, created_at, updated_at) VALUES (',
  QUOTE(school_id), ', ', QUOTE(user_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM school_users WHERE school_id = @SCHOOL_ID;

-- 3. Temporadas de la escuela
SELECT CONCAT(
  'INSERT INTO seasons (id, name, school_id, start_date, end_date, is_active, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(name), ', ', QUOTE(school_id), ', ', QUOTE(start_date), ', ', 
  QUOTE(end_date), ', ', QUOTE(is_active), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM seasons WHERE school_id = @SCHOOL_ID;

-- 4. Deportes de la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO school_sports (id, school_id, sport_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(sport_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM school_sports WHERE school_id = @SCHOOL_ID;

-- 5. Grados/Niveles de la escuela
SELECT CONCAT(
  'INSERT INTO degrees (id, league, level, name, annotation, degree_order, progress, color, image, age_min, age_max, active, school_id, sport_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(league), ', ', QUOTE(level), ', ', QUOTE(name), ', ', QUOTE(annotation), ', ', 
  QUOTE(degree_order), ', ', QUOTE(progress), ', ', QUOTE(color), ', ', QUOTE(image), ', ', 
  QUOTE(age_min), ', ', QUOTE(age_max), ', ', QUOTE(active), ', ', QUOTE(school_id), ', ', 
  QUOTE(sport_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM degrees WHERE school_id = @SCHOOL_ID;

-- 6. Colores de la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO school_colors (id, school_id, color, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(color), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM school_colors WHERE school_id = @SCHOOL_ID;

-- 7. Niveles salariales de la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO school_salary_levels (id, school_id, name, amount, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(name), ', ', QUOTE(amount), ', ', 
  QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM school_salary_levels WHERE school_id = @SCHOOL_ID;

-- 8. Estaciones asociadas a la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO stations_schools (id, station_id, school_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(station_id), ', ', QUOTE(school_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM stations_schools WHERE school_id = @SCHOOL_ID;

-- ===========================================
-- CLIENTES Y MONITORES ASOCIADOS
-- ===========================================

-- 9. Clientes asociados a la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO clients_schools (id, client_id, school_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(client_id), ', ', QUOTE(school_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM clients_schools WHERE school_id = @SCHOOL_ID;

-- 10. Monitores asociados a la escuela
SELECT CONCAT(
  'INSERT IGNORE INTO monitors_schools (id, monitor_id, school_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(monitor_id), ', ', QUOTE(school_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM monitors_schools WHERE school_id = @SCHOOL_ID;

-- ===========================================
-- CURSOS Y RESERVAS
-- ===========================================

-- 11. Cursos de la escuela
SELECT CONCAT(
  'INSERT INTO courses (id, school_id, name, description, type, max_people, duration, price, active, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(name), ', ', QUOTE(description), ', ', 
  QUOTE(type), ', ', QUOTE(max_people), ', ', QUOTE(duration), ', ', QUOTE(price), ', ', 
  QUOTE(active), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM courses WHERE school_id = @SCHOOL_ID;

-- 12. Fechas de cursos
SELECT CONCAT(
  'INSERT INTO course_dates (id, course_id, date, start_time, end_time, available_spots, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(course_id), ', ', QUOTE(date), ', ', QUOTE(start_time), ', ', 
  QUOTE(end_time), ', ', QUOTE(available_spots), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM course_dates WHERE course_id IN (SELECT id FROM courses WHERE school_id = @SCHOOL_ID);

-- 13. Extras de cursos
SELECT CONCAT(
  'INSERT INTO course_extras (id, course_id, name, price, description, active, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(course_id), ', ', QUOTE(name), ', ', QUOTE(price), ', ', 
  QUOTE(description), ', ', QUOTE(active), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM course_extras WHERE course_id IN (SELECT id FROM courses WHERE school_id = @SCHOOL_ID);

-- 14. Grupos de cursos
SELECT CONCAT(
  'INSERT INTO course_groups (id, course_id, name, max_participants, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(course_id), ', ', QUOTE(name), ', ', QUOTE(max_participants), ', ', 
  QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM course_groups WHERE course_id IN (SELECT id FROM courses WHERE school_id = @SCHOOL_ID);

-- 15. Subgrupos de cursos
SELECT CONCAT(
  'INSERT INTO course_subgroups (id, group_id, name, max_participants, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(group_id), ', ', QUOTE(name), ', ', QUOTE(max_participants), ', ', 
  QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM course_subgroups WHERE group_id IN (SELECT id FROM course_groups WHERE course_id IN (SELECT id FROM courses WHERE school_id = @SCHOOL_ID));

-- 16. Reservas de la escuela
SELECT CONCAT(
  'INSERT INTO bookings (id, school_id, user_id, course_id, client_id, status, total_amount, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(user_id), ', ', QUOTE(course_id), ', ', 
  QUOTE(client_id), ', ', QUOTE(status), ', ', QUOTE(total_amount), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM bookings WHERE school_id = @SCHOOL_ID;

-- ===========================================
-- DATOS DEPENDIENTES DE RESERVAS
-- ===========================================

-- 17. Usuarios de reservas
SELECT CONCAT(
  'INSERT INTO booking_users (id, booking_id, client_id, status, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(booking_id), ', ', QUOTE(client_id), ', ', QUOTE(status), ', ', 
  QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM booking_users WHERE booking_id IN (SELECT id FROM bookings WHERE school_id = @SCHOOL_ID);

-- 18. Extras de reservas de usuarios
SELECT CONCAT(
  'INSERT INTO booking_user_extras (id, booking_user_id, extra_id, quantity, price, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(booking_user_id), ', ', QUOTE(extra_id), ', ', QUOTE(quantity), ', ', 
  QUOTE(price), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM booking_user_extras WHERE booking_user_id IN (
  SELECT id FROM booking_users WHERE booking_id IN (SELECT id FROM bookings WHERE school_id = @SCHOOL_ID)
);

-- 19. Pagos de reservas
SELECT CONCAT(
  'INSERT INTO payments (id, booking_id, amount, status, payment_method, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(booking_id), ', ', QUOTE(amount), ', ', QUOTE(status), ', ', 
  QUOTE(payment_method), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE school_id = @SCHOOL_ID);

-- 20. Logs de reservas
SELECT CONCAT(
  'INSERT INTO booking_logs (id, booking_id, action, description, user_id, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(booking_id), ', ', QUOTE(action), ', ', QUOTE(description), ', ', 
  QUOTE(user_id), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM booking_logs WHERE booking_id IN (SELECT id FROM bookings WHERE school_id = @SCHOOL_ID);

-- 21. Evaluaciones
SELECT CONCAT(
  'INSERT INTO evaluations (id, client_id, course_id, monitor_id, school_id, rating, comments, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(client_id), ', ', QUOTE(course_id), ', ', QUOTE(monitor_id), ', ', 
  QUOTE(school_id), ', ', QUOTE(rating), ', ', QUOTE(comments), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM evaluations WHERE school_id = @SCHOOL_ID;

-- 22. Vouchers de la escuela
SELECT CONCAT(
  'INSERT INTO vouchers (id, school_id, code, discount_type, discount_value, valid_from, valid_until, usage_limit, times_used, active, created_at, updated_at) VALUES (',
  QUOTE(id), ', ', QUOTE(school_id), ', ', QUOTE(code), ', ', QUOTE(discount_type), ', ', 
  QUOTE(discount_value), ', ', QUOTE(valid_from), ', ', QUOTE(valid_until), ', ', 
  QUOTE(usage_limit), ', ', QUOTE(times_used), ', ', QUOTE(active), ', ', QUOTE(created_at), ', ', QUOTE(updated_at), ');'
) AS sql_statement
FROM vouchers WHERE school_id = @SCHOOL_ID;

-- Finalización
SELECT 'SET foreign_key_checks = 1;' AS sql_statement;