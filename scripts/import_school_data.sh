#!/bin/bash

# Script para importar datos de una escuela específica a producción
# Uso: ./import_school_data.sh <ARCHIVO_EXPORTACION> <DB_NAME_PROD> <DB_USER> <DB_PASS>

IMPORT_FILE=${1}
DB_NAME=${2:-boukii_pro}
DB_USER=${3:-root}
DB_PASS=${4:-""}
BACKUP_FILE="backup_before_import_$(date +%Y%m%d_%H%M%S).sql"

# Validaciones iniciales
if [ ! -f "$IMPORT_FILE" ]; then
    echo "❌ Error: Archivo de importación no encontrado: $IMPORT_FILE"
    exit 1
fi

echo "🔄 IMPORTACIÓN DE DATOS DE ESCUELA A PRODUCCIÓN"
echo "=============================================="
echo "📁 Archivo a importar: $IMPORT_FILE"
echo "🗄️  Base de datos destino: $DB_NAME"
echo "👤 Usuario: $DB_USER"
echo ""

# Confirmación de seguridad
read -p "⚠️  ¿Estás seguro de que quieres importar a PRODUCCIÓN? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "❌ Importación cancelada."
    exit 0
fi

# Función para ejecutar SQL y capturar errores
execute_sql() {
    local query="$1"
    local description="$2"
    
    echo "⚡ $description"
    if ! mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "$query"; then
        echo "❌ Error ejecutando: $description"
        return 1
    fi
    return 0
}

# Función para validar conectividad
validate_connection() {
    echo "🔌 Validando conexión a la base de datos..."
    if ! mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
        echo "❌ Error: No se puede conectar a la base de datos $DB_NAME"
        exit 1
    fi
    echo "✅ Conexión exitosa"
}

# Función para crear backup de seguridad
create_backup() {
    echo "💾 Creando backup de seguridad..."
    
    # Obtener ID de la escuela del archivo de importación
    SCHOOL_ID=$(grep -o "INSERT INTO \`schools\`" -A 1 "$IMPORT_FILE" | grep -o "VALUES ([0-9]*" | grep -o "[0-9]*" | head -1)
    
    if [ ! -z "$SCHOOL_ID" ]; then
        echo "🔍 Escuela ID detectada: $SCHOOL_ID"
        
        # Backup solo si la escuela ya existe
        SCHOOL_EXISTS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM schools WHERE id = $SCHOOL_ID;")
        
        if [ "$SCHOOL_EXISTS" -gt 0 ]; then
            echo "⚠️  La escuela ya existe. Creando backup..."
            
            # Lista de tablas a respaldar
            TABLES_TO_BACKUP=(
                "schools"
                "seasons"
                "school_users" 
                "school_sports"
                "degrees"
                "courses"
                "bookings"
                "booking_users"
                "payments"
                "vouchers"
            )
            
            echo "-- BACKUP ANTES DE IMPORTAR ESCUELA $SCHOOL_ID --" > "$BACKUP_FILE"
            echo "-- Fecha: $(date)" >> "$BACKUP_FILE"
            echo "" >> "$BACKUP_FILE"
            
            for table in "${TABLES_TO_BACKUP[@]}"; do
                echo "💾 Respaldando tabla: $table"
                mysqldump -u "$DB_USER" -p"$DB_PASS" --where="school_id = $SCHOOL_ID OR id = $SCHOOL_ID" "$DB_NAME" "$table" >> "$BACKUP_FILE" 2>/dev/null
            done
            
            echo "✅ Backup creado: $BACKUP_FILE"
        else
            echo "ℹ️  Escuela nueva - no se requiere backup"
        fi
    fi
}

# Función para validar integridad antes de importar
validate_import_file() {
    echo "🔍 Validando archivo de importación..."
    
    # Verificar que el archivo contenga datos de escuela
    if ! grep -q "INSERT INTO \`schools\`" "$IMPORT_FILE"; then
        echo "❌ Error: El archivo no contiene datos de escuelas"
        exit 1
    fi
    
    # Verificar sintaxis SQL básica
    if grep -q "ERROR\|FAILED" "$IMPORT_FILE"; then
        echo "❌ Error: El archivo contiene errores"
        exit 1
    fi
    
    echo "✅ Archivo válido"
}

# Función para ejecutar la importación
perform_import() {
    echo "📥 Ejecutando importación..."
    
    # Crear archivo temporal con configuraciones adicionales
    TEMP_IMPORT_FILE="temp_import_$(date +%Y%m%d_%H%M%S).sql"
    
    cat > "$TEMP_IMPORT_FILE" << 'EOF'
-- CONFIGURACIÓN PARA IMPORTACIÓN SEGURA
SET foreign_key_checks = 0;
SET sql_mode = '';
SET autocommit = 0;
SET unique_checks = 0;

START TRANSACTION;

EOF
    
    # Agregar el contenido del archivo original
    cat "$IMPORT_FILE" >> "$TEMP_IMPORT_FILE"
    
    # Finalizar la transacción
    cat >> "$TEMP_IMPORT_FILE" << 'EOF'

COMMIT;
SET foreign_key_checks = 1;
SET unique_checks = 1;

-- VERIFICACIÓN POST-IMPORTACIÓN
SELECT 'IMPORTACIÓN COMPLETADA' as status;
EOF
    
    # Ejecutar importación
    echo "⚡ Aplicando cambios..."
    if mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" < "$TEMP_IMPORT_FILE"; then
        echo "✅ Importación exitosa"
        
        # Limpiar archivo temporal
        rm "$TEMP_IMPORT_FILE"
        
        return 0
    else
        echo "❌ Error en la importación"
        
        # Mantener archivo temporal para debug
        echo "🔧 Archivo temporal conservado para debug: $TEMP_IMPORT_FILE"
        
        return 1
    fi
}

# Función para validar post-importación
validate_post_import() {
    echo "🔍 Validando datos importados..."
    
    # Extraer ID de escuela del backup
    SCHOOL_ID=$(grep -o "INSERT INTO \`schools\`" -A 1 "$IMPORT_FILE" | grep -o "VALUES ([0-9]*" | grep -o "[0-9]*" | head -1)
    
    if [ ! -z "$SCHOOL_ID" ]; then
        # Verificar que la escuela se importó correctamente
        SCHOOL_EXISTS=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM schools WHERE id = $SCHOOL_ID;")
        
        if [ "$SCHOOL_EXISTS" -gt 0 ]; then
            echo "✅ Escuela importada correctamente (ID: $SCHOOL_ID)"
            
            # Mostrar estadísticas
            echo ""
            echo "📊 ESTADÍSTICAS DE IMPORTACIÓN"
            echo "=============================="
            
            COURSES_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM courses WHERE school_id = $SCHOOL_ID;" 2>/dev/null || echo "0")
            BOOKINGS_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM bookings WHERE school_id = $SCHOOL_ID;" 2>/dev/null || echo "0")
            SEASONS_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM seasons WHERE school_id = $SCHOOL_ID;" 2>/dev/null || echo "0")
            CLIENTS_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -se "SELECT COUNT(*) FROM clients_schools WHERE school_id = $SCHOOL_ID;" 2>/dev/null || echo "0")
            
            echo "📚 Cursos: $COURSES_COUNT"
            echo "📋 Reservas: $BOOKINGS_COUNT"
            echo "📅 Temporadas: $SEASONS_COUNT"
            echo "👥 Clientes asociados: $CLIENTS_COUNT"
            
            return 0
        else
            echo "❌ Error: La escuela no se encontró después de la importación"
            return 1
        fi
    fi
    
    return 1
}

# EJECUCIÓN PRINCIPAL
echo "🚀 Iniciando proceso de importación..."
echo ""

# 1. Validar conexión
validate_connection

# 2. Validar archivo
validate_import_file

# 3. Crear backup
create_backup

# 4. Ejecutar importación
if perform_import; then
    
    # 5. Validar resultado
    if validate_post_import; then
        echo ""
        echo "🎉 IMPORTACIÓN COMPLETADA EXITOSAMENTE"
        echo "========================================"
        echo "📁 Archivo importado: $IMPORT_FILE"
        echo "💾 Backup creado: $BACKUP_FILE"
        echo "🕒 Fecha: $(date)"
        echo ""
        echo "✅ La escuela ha sido migrada correctamente a producción."
        
    else
        echo ""
        echo "⚠️  IMPORTACIÓN COMPLETADA CON ADVERTENCIAS"
        echo "==========================================="
        echo "Los datos se importaron pero hay problemas en la validación."
        echo "Revisa manualmente la base de datos."
    fi
    
else
    echo ""
    echo "❌ IMPORTACIÓN FALLIDA"
    echo "====================="
    echo "💾 Se mantiene el backup: $BACKUP_FILE"
    echo "🔧 Revisa los logs de error para más detalles."
    exit 1
fi