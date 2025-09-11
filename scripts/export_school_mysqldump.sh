#!/bin/bash

# Script para exportar datos de una escuela específica usando mysqldump
# Uso: ./export_school_mysqldump.sh <SCHOOL_ID> <DB_NAME> <DB_USER> <DB_PASS>

SCHOOL_ID=${1:-1}
DB_NAME=${2:-boukii_develop}
DB_USER=${3:-root}
DB_PASS=${4:-""}
OUTPUT_FILE="school_${SCHOOL_ID}_export_$(date +%Y%m%d_%H%M%S).sql"

echo "🏫 Exportando datos de la escuela ID: $SCHOOL_ID"
echo "📁 Archivo de salida: $OUTPUT_FILE"

# Función para obtener IDs relacionados
get_related_ids() {
    local table=$1
    local column=$2
    local value=$3
    local db=$4
    
    mysql -u "$DB_USER" -p"$DB_PASS" -D "$db" -se "SELECT GROUP_CONCAT(id) FROM $table WHERE $column = $value;"
}

# Obtener IDs de tablas relacionadas
echo "🔍 Obteniendo IDs de tablas relacionadas..."

COURSE_IDS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT GROUP_CONCAT(id) FROM courses WHERE school_id = $SCHOOL_ID;")
BOOKING_IDS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT GROUP_CONCAT(id) FROM bookings WHERE school_id = $SCHOOL_ID;")
SEASON_IDS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT GROUP_CONCAT(id) FROM seasons WHERE school_id = $SCHOOL_ID;")

echo "📚 Cursos encontrados: ${COURSE_IDS:-'ninguno'}"
echo "📋 Reservas encontradas: ${BOOKING_IDS:-'ninguna'}"
echo "📅 Temporadas encontradas: ${SEASON_IDS:-'ninguna'}"

# Crear archivo de exportación
cat > "$OUTPUT_FILE" << 'EOF'
-- =====================================================
-- EXPORTACIÓN DE DATOS DE ESCUELA ESPECÍFICA
-- =====================================================

SET foreign_key_checks = 0;
SET sql_mode = '';
SET autocommit = 0;

START TRANSACTION;

EOF

# Exportar tabla principal: schools
echo "📊 Exportando tabla: schools"
mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="id = $SCHOOL_ID" "$DB_NAME" schools >> "$OUTPUT_FILE"

# Exportar tablas directamente relacionadas con school_id
DIRECT_SCHOOL_TABLES=(
    "seasons"
    "school_users" 
    "school_sports"
    "degrees"
    "school_colors"
    "school_salary_levels"
    "stations_schools"
    "clients_schools"
    "monitors_schools"
    "vouchers"
    "evaluations"
)

for table in "${DIRECT_SCHOOL_TABLES[@]}"; do
    echo "📊 Exportando tabla: $table"
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="school_id = $SCHOOL_ID" "$DB_NAME" "$table" >> "$OUTPUT_FILE" 2>/dev/null
done

# Exportar cursos si existen
if [ ! -z "$COURSE_IDS" ]; then
    echo "📚 Exportando cursos y datos relacionados..."
    
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="school_id = $SCHOOL_ID" "$DB_NAME" courses >> "$OUTPUT_FILE"
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="course_id IN ($COURSE_IDS)" "$DB_NAME" course_dates >> "$OUTPUT_FILE" 2>/dev/null
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="course_id IN ($COURSE_IDS)" "$DB_NAME" course_extras >> "$OUTPUT_FILE" 2>/dev/null
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="course_id IN ($COURSE_IDS)" "$DB_NAME" course_groups >> "$OUTPUT_FILE" 2>/dev/null
    
    # Subgrupos de cursos
    COURSE_GROUP_IDS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT GROUP_CONCAT(id) FROM course_groups WHERE course_id IN ($COURSE_IDS);")
    if [ ! -z "$COURSE_GROUP_IDS" ]; then
        mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="group_id IN ($COURSE_GROUP_IDS)" "$DB_NAME" course_subgroups >> "$OUTPUT_FILE" 2>/dev/null
    fi
fi

# Exportar reservas si existen
if [ ! -z "$BOOKING_IDS" ]; then
    echo "📋 Exportando reservas y datos relacionados..."
    
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="school_id = $SCHOOL_ID" "$DB_NAME" bookings >> "$OUTPUT_FILE"
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="booking_id IN ($BOOKING_IDS)" "$DB_NAME" booking_users >> "$OUTPUT_FILE" 2>/dev/null
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="booking_id IN ($BOOKING_IDS)" "$DB_NAME" booking_logs >> "$OUTPUT_FILE" 2>/dev/null
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="booking_id IN ($BOOKING_IDS)" "$DB_NAME" payments >> "$OUTPUT_FILE" 2>/dev/null
    
    # Extras de booking_users
    BOOKING_USER_IDS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT GROUP_CONCAT(id) FROM booking_users WHERE booking_id IN ($BOOKING_IDS);")
    if [ ! -z "$BOOKING_USER_IDS" ]; then
        mysqldump -u "$DB_USER" -p"$DB_PASS" --no-create-info --where="booking_user_id IN ($BOOKING_USER_IDS)" "$DB_NAME" booking_user_extras >> "$OUTPUT_FILE" 2>/dev/null
    fi
fi

# Finalizar
cat >> "$OUTPUT_FILE" << 'EOF'

COMMIT;
SET foreign_key_checks = 1;

-- =====================================================
-- FIN DE EXPORTACIÓN
-- =====================================================
EOF

echo "✅ Exportación completada: $OUTPUT_FILE"
echo "📁 Tamaño del archivo: $(du -h "$OUTPUT_FILE" | cut -f1)"
echo ""
echo "🚀 Para importar en producción:"
echo "   mysql -u usuario -p base_datos_pro < $OUTPUT_FILE"