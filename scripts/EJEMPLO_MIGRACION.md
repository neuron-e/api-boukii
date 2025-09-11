# 🚀 Ejemplo Práctico de Migración de Escuela

Esta guía muestra cómo migrar la escuela **"School Testing" (ID: 1)** desde desarrollo a producción.

## 📊 Estado Actual de la Escuela

Según la validación realizada, la escuela tiene:

```
Escuela: School Testing (ID: 1)
├── Cursos: 8
├── Reservas: 104  
├── Temporadas: 2
├── Usuarios asociados: 18
├── Clientes asociados: 48
├── Grados/Niveles: 13
└── Deportes: 2 (Ski, Snowboard)
```

## 🎯 Pasos de Migración Completa

### 1. VALIDACIÓN INICIAL (Desarrollo)

```bash
cd /path/to/api-boukii

# Validar integridad de datos de la escuela
php artisan boukii:migrate-school 1 validate
```

**Resultado esperado:**
- ✅ Datos íntegros sin problemas de referencia
- 📊 Estadísticas completas de la escuela
- 🔍 Verificación de relaciones

### 2. EXPORTACIÓN (Desarrollo)

```bash
# Exportar todos los datos de la escuela
php artisan boukii:migrate-school 1 export
```

**Archivo generado:**
- 📁 `storage/app/exports/school_1_export_2025-09-03_08-01-30.sql`
- 📏 Tamaño: ~936 KB
- 📋 Contiene: 22+ tablas con todas las relaciones

### 3. PREPARACIÓN (Producción)

```bash
# En el servidor de producción
cd /path/to/api-boukii

# 1. Crear backup completo
mysqldump -u root -p --routines --triggers boukii_pro > backup_completo_$(date +%Y%m%d).sql

# 2. Subir archivo exportado
scp storage/app/exports/school_1_export_2025-09-03_08-01-30.sql servidor-prod:/path/to/api-boukii/

# 3. Validar conexión a BD
mysql -u root -p -e "SELECT COUNT(*) as escuelas_actuales FROM schools;" boukii_pro
```

### 4. IMPORTACIÓN SEGURA (Producción)

```bash
# DRY RUN - Ver qué pasaría sin ejecutar cambios
php artisan boukii:migrate-school 1 import \
    --file=storage/app/exports/school_1_export_2025-09-03_08-01-30.sql \
    --dry-run

# IMPORTACIÓN REAL - Solo después del dry-run exitoso
php artisan boukii:migrate-school 1 import \
    --file=storage/app/exports/school_1_export_2025-09-03_08-01-30.sql
```

### 5. VERIFICACIÓN POST-MIGRACIÓN (Producción)

```bash
# Validar que los datos se importaron correctamente
php artisan boukii:migrate-school 1 validate

# Verificar usuarios de prueba
php artisan tinker
>>> App\Models\User::whereIn('email', ['superadmin@boukii-v5.com', 'admin.single@boukii-v5.com'])->get();

# Probar autenticación V5
php test_v5_auth.php
```

## ⚡ Método Alternativo con Scripts Bash

Si prefieres usar los scripts Bash independientes:

### Exportación:

```bash
cd scripts/
./export_school_mysqldump.sh 1 boukii_develop root ""
```

### Importación:

```bash
./import_school_data.sh school_1_export_20250903_080130.sql boukii_pro root "password"
```

## 📋 Checklist de Verificación

### ✅ Pre-Migración
- [ ] Backup completo de producción creado
- [ ] Archivo de exportación generado (936 KB)
- [ ] Conexión a BD de producción verificada
- [ ] Notificación a usuarios sobre mantenimiento

### ✅ Durante Migración
- [ ] Dry-run ejecutado sin errores
- [ ] Backup específico pre-importación creado
- [ ] Importación real exitosa
- [ ] Logs revisados sin errores críticos

### ✅ Post-Migración
- [ ] Validación de datos completada
- [ ] Login de usuarios V5 funcionando
- [ ] Estadísticas coinciden con desarrollo:
  - [ ] 8 cursos importados
  - [ ] 104 reservas importadas  
  - [ ] 2 temporadas importadas
  - [ ] 18 usuarios asociados
  - [ ] 48 clientes asociados
- [ ] Funcionalidad básica de reservas operativa

## 🆘 Plan de Rollback

Si algo sale mal durante la importación:

```bash
# 1. STOP - Detener todos los servicios
sudo service nginx stop
sudo service php8.2-fpm stop

# 2. RESTORE - Restaurar desde backup
mysql -u root -p boukii_pro < backup_completo_20250903.sql

# 3. RESTART - Reiniciar servicios  
sudo service php8.2-fpm start
sudo service nginx start

# 4. VALIDATE - Verificar funcionamiento
php artisan boukii:migrate-school 1 validate
```

## 📊 Datos Específicos de Esta Migración

```sql
-- Escuela principal
INSERT INTO schools (id=1, name='School Testing', slug='SchoolTesting'...)

-- Temporadas (2)
- Temporada 1 (ID: 5) - Activa: 2024-12-14 a 2025-03-15
- Prev Season 2023 (ID: 10) - Inactiva: 2023-01-01 a 2023-12-31

-- Usuarios asociados (18)
- IDs: 17534, 17539, 17556, 17558, 17563, 17569, 17575, 17606, 17607...
- Incluye usuarios V5 de prueba: 20213, 20214, 20215, 20216, 20217

-- Deportes (2)  
- Ski (ID: 1)
- Snowboard (ID: 2)

-- Grados/Niveles (13)
- SKV: Ptit Loup, JN, Débutant
- BLEU: Prince/Princesse, Roi/Reine, Star
- ROUGE: Prince/Princesse, Roi/Reine, Star  
- NOIR: Prince/Princesse
- Academy: Race, Freestyle, Freeride
```

## 🔗 Archivos Relacionados

- 📄 `storage/app/exports/school_1_export_2025-09-03_08-01-30.sql` - Datos exportados
- 📄 `scripts/README_MIGRACION_ESCUELAS.md` - Documentación completa
- 📄 `test_v5_auth.php` - Script de prueba de autenticación V5
- 🔧 `MigrateSchoolDataCommand.php` - Comando Laravel principal

## ⏱️ Tiempo Estimado

- **Exportación**: 2-3 minutos
- **Transferencia**: 1-2 minutos  
- **Importación**: 3-5 minutos
- **Verificación**: 2-3 minutos
- **Total**: ~10-15 minutos

---

**💡 Recuerda**: Siempre hacer `--dry-run` antes de importar en producción.