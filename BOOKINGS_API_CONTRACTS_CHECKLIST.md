# Admin Bookings API Contracts – Checklist (Review)

Objetivo: alinear contratos y respuestas de la API con el panel Admin Bookings V2 sin cambios breaking. Este documento sirve de guía para revisar/ajustar documentación y respuestas.

## 1) Creación de reserva
- Endpoint: `POST /api/admin/bookings`
- Request (ejemplo mínimo):
```json
{
  "client_main_id": 123,
  "payment_method_id": 1,
  "cart": [
    {
      "client_id": 321,
      "course_id": 45,
      "course_type": 2,
      "group_id": 9001,
      "course_date_id": 777,
      "hour_start": "10:00",
      "hour_end": "12:00",
      "degree_id": 6,
      "monitor_id": 55,
      "price_base": 100,
      "extra_price": 20,
      "price": 120,
      "extras": [
        {"course_extra_id": 1, "quantity": 2, "price": 10}
      ],
      "notes": "...",
      "notes_school": "..."
    }
  ]
}
```
- Response: booking + relaciones básicas y `basket` (si aplica para pagos online).

## 2) Actualizar método/estado de pago
- Endpoint: `POST /api/admin/bookings/update/{id}/payment`
- Request (ejemplo):
```json
{
  "payment_method_id": 2,
  "paid": false,
  "paid_total": 0,
  "selectedPaymentOption": "Boukii Pay" 
}
```
- Response:
```json
{
  "data": {
    "id": 999,
    "price_total": 120,
    "paid": false,
    "paid_total": 0,
    "voucher_logs": [],
    "basket": { /* datos necesarios para /payments/{id} */ }
  }
}
```
- Idempotencia: repetir esta llamada no debe duplicar pagos/logs.

## 3) Iniciar pago online
- Endpoint: `POST /api/admin/bookings/payments/{id}`
- Request: `basket` devuelto por `update/{id}/payment`.
- Response:
  - Para `payment_method_id = 2` (gateway): string URL para redirección inmediata.
  - Para `payment_method_id = 3` (paylink): status ok y notificación de email enviado.

## 4) Cancelación
- Endpoint: `POST /api/admin/bookings/cancel`
- Request:
```json
{ "bookingUsers": [111, 112, 113] }
```
- Response:
```json
{
  "data": {
    "id": 999,
    "status": 3,
    "price_total": 120,
    "voucher_logs": [
      {"bonus": {"reducePrice": 20}}
    ],
    "booking_users": [ {"id":111, "status":2}, {"id":112, "status":2} ]
  }
}
```
- Nota: aclarar si `price_total` se recalcula en servidor tras cancelación; si no, UI recalcula totales por actividad.

## 5) Check de solapes
- Endpoint: `POST /api/admin/bookings/checkbooking`
- Request: datos de fechas/horas/monitor/curso necesarios.
- Response 409 (sugerido estable):
```json
{
  "overlaps": [
    {"course_date_id": 777, "hour_start": "10:00", "hour_end": "12:00", "reason": "monitor_unavailable"}
  ]
}
```

## 6) Monitores disponibles
- Endpoint: `POST /api/admin/monitors/available`
- Request: `sport_id`, `min_degree_id`, `languages[]`, `date`, `hour_start`, `hour_end`.
- Response: lista de monitores con `monitor_id`, `name`, `degrees[]`, `languages[]`, `is_available`.

---
Acciones sugeridas:
- Añadir estos ejemplos a `backend/api-documentation.md`.
- Definir claramente qué campos de `cart[]` son requeridos vs opcionales.
- Asegurar que `update/{id}/payment` es idempotente y que siempre devuelve `basket` cuando aplica.
- Estabilizar el shape de 409 en `checkbooking` para soportar un feedback consistente en la UI.

