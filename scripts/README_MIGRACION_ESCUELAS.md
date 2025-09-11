# 🏫 Migración de Datos de Escuelas

Este conjunto de scripts permite migrar datos de una escuela específica desde el entorno de desarrollo hacia producción de manera segura.

## 📁 Archivos Incluidos

1. **`export_school_data.sql`** - Script SQL manual para exportar datos
2. **`export_school_mysqldump.sh`** - Script Bash automatizado para exportación  
3. **`import_school_data.sh`** - Script Bash para importación segura
4. **`MigrateSchoolDataCommand.php`** - Comando Laravel completo

## 🚀 Uso Rápido

### Opción 1: Comando Laravel (Recomendado)

```bash
# 1. Exportar datos de escuela (desde develop)
php artisan boukii:migrate-school 123 export

# 2. Validar datos antes de migrar
php artisan boukii:migrate-school 123 validate

# 3. Importar datos (en production) - DRY RUN primero
php artisan boukii:migrate-school 123 import --file=storage/app/exports/school_123_export_2025-01-15_10-30-00.sql --dry-run

# 4. Importar datos realmente
php artisan boukii:migrate-school 123 import --file=storage/app/exports/school_123_export_2025-01-15_10-30-00.sql
```

### Opción 2: Scripts Bash

```bash
# 1. Exportar (desde develop)
./export_school_mysqldump.sh 123 boukii_develop root ""

# 2. Importar (en production)
./import_school_data.sh school_123_export_20250115_103000.sql boukii_pro root "password"
```

### Opción 3: SQL Manual

```bash
# 1. Editar el archivo export_school_data.sql
# Cambiar: SET @SCHOOL_ID = 123;

# 2. Ejecutar exportación
mysql -u root -p boukii_develop < export_school_data.sql > escuela_123_datos.sql

# 3. Importar en producción
mysql -u root -p boukii_pro < escuela_123_datos.sql
```

## 📊 Datos que se Migran

### ✅ Datos Principales
- **Escuela** - Configuración completa
- **Temporadas** - Todas las temporadas activas
- **Usuarios** - Asociaciones escuela-usuario
- **Deportes** - Configuración de deportes
- **Grados/Niveles** - Sistema de niveles
- **Configuraciones** - Colores, salarios, estaciones

### 📚 Cursos y Actividades
- **Cursos** - Todos los cursos de la escuela
- **Fechas de Cursos** - Horarios y disponibilidad
- **Extras** - Complementos y servicios adicionales
- **Grupos** - Organización de estudiantes
- **Subgrupos** - División detallada

### 📋 Reservas y Pagos
- **Reservas** - Todas las reservas realizadas
- **Usuarios de Reservas** - Participantes
- **Pagos** - Historial de pagos
- **Logs** - Seguimiento de cambios
- **Extras** - Servicios adicionales contratados

### 👥 Relaciones
- **Clientes** - Asociaciones cliente-escuela
- **Monitores** - Personal asignado
- **Evaluaciones** - Calificaciones y comentarios
- **Vouchers** - Cupones y descuentos

## ⚠️ Consideraciones Importantes

### 🔒 Seguridad
- **Siempre crear backup** antes de importar
- **Usar dry-run** para validar antes de ejecutar
- **Validar conexiones** a las bases de datos
- **Mantener logs** de las operaciones

### 🎯 Integridad
- Los scripts mantienen **integridad referencial**
- Se exportan **todas las dependencias**
- Se preservan **IDs originales**
- Se validan **datos post-importación**

### 📈 Rendimiento  
- Los scripts están **optimizados** para escuelas grandes
- Usan **transacciones** para consistencia
- **Progreso visible** durante la ejecución
- **Manejo de errores** robusto

## 🔧 Solución de Problemas

### Error: "Escuela no encontrada"
```bash
# Verificar que el ID de escuela existe
php artisan tinker
>>> App\Models\School::find(123)
```

### Error: "Cannot connect to database"
```bash
# Verificar configuración de base de datos
php artisan config:cache
mysql -u usuario -p -e "SELECT 1;" nombre_db
```

### Error: "Foreign key constraint fails"
```bash
# El script maneja esto automáticamente, pero si persiste:
# 1. Verificar que todas las tablas relacionadas existen
# 2. Ejecutar con --dry-run primero
# 3. Revisar logs de importación
```

## 📋 Checklist de Migración

### Antes de Migrar
- [ ] Identificar ID correcto de la escuela
- [ ] Verificar conexión a base de datos de desarrollo
- [ ] Verificar conexión a base de datos de producción
- [ ] Crear backup completo de producción
- [ ] Notificar a usuarios de posible downtime

### Durante la Migración
- [ ] Ejecutar exportación en desarrollo
- [ ] Validar archivo exportado
- [ ] Ejecutar dry-run en producción
- [ ] Crear backup específico pre-importación
- [ ] Ejecutar importación real
- [ ] Validar datos importados

### Después de Migrar
- [ ] Verificar funcionamiento de la escuela
- [ ] Comprobar login de usuarios
- [ ] Validar reservas y pagos
- [ ] Monitorear logs de errores
- [ ] Documentar migración realizada

## 🆘 Recuperación de Errores

Si algo sale mal durante la importación:

1. **STOP** - Detener inmediatamente cualquier operación
2. **BACKUP** - Restaurar desde el backup automático creado
3. **LOG** - Revisar logs detallados del error
4. **FIX** - Corregir el problema identificado
5. **RETRY** - Intentar nuevamente con dry-run

## 📞 Contacto

Para problemas o mejoras en estos scripts, contacta al equipo de desarrollo.

---

**⚡ Recuerda: Siempre usar `--dry-run` primero en producción**