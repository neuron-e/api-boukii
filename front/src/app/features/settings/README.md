# Settings

Módulo de configuración de la escuela.

## Estructura

```
settings/
├── data/                # Datos mock usados mientras no hay backend
├── school/              # Configuración de datos de la escuela
├── seasons/             # Gestión de temporadas
├── sports-degrees/      # Configuración de grados deportivos
├── station/             # Selección de estaciones
├── settings.routes.ts   # Definición de rutas del módulo
├── settings.service.ts  # Servicio con lógica de negocio (mock)
└── index.ts             # Punto de entrada para exports
```

## Datos mock

Los datos simulados se encuentran en `data/`. Estos archivos proporcionan
las colecciones que consume `SettingsService` mientras no existe conexión con el backend.
Cuando se integre el backend deben reemplazarse los métodos de `settings.service.ts`
(`getAllSports`, `getSchoolSettings`, `getMockSeasons`, `getMockDegrees`, `getStationSettings`,
`getMockStations`, `getMockSchool`), sustituyendo las referencias a `data/` por llamadas HTTP
reales. En particular `getMockStations` incluye un TODO para usar los endpoints
`/api/stations` y `/api/stations-schools`.

## Ejecución en modo mock

1. `cd front`
2. `npm install`
3. `npm start`

El servidor de desarrollo se levanta con los datos mock incluidos y no requiere backend.

