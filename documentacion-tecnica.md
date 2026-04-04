# DOCUMENTACION TECNICA - SISTEMA TAXIVAN

**Sistema de Gestion de Flota de Taxis**
**Version:** Laravel 11 | Livewire 3
**Fecha de generacion:** 2026-04-01
**Proyecto:** laravel_taxivan

---

## INDICE

1. [Resumen General](#1-resumen-general)
2. [Base de Datos](#2-base-de-datos)
3. [Modelos](#3-modelos)
4. [Controladores](#4-controladores)
5. [Componentes Livewire](#5-componentes-livewire)
6. [Rutas](#6-rutas)
7. [Vistas](#7-vistas)
8. [Exports](#8-exports)
9. [Servicios](#9-servicios)
10. [Comandos Artisan](#10-comandos-artisan)
11. [Scheduler](#11-scheduler)
12. [Sistema de Roles y Permisos](#12-sistema-de-roles-y-permisos)
13. [Policies](#13-policies)
14. [Traits](#14-traits)
15. [Assets](#15-assets)
16. [Configuraciones Clave](#16-configuraciones-clave)
17. [Deploy](#17-deploy)
18. [Seeders](#18-seeders)

---

## 1. RESUMEN GENERAL

### 1.1 Descripcion del Sistema

TaxiVan es un sistema de gestion de flota vehicular desarrollado en Laravel 11. Permite administrar vehiculos, conductores, propietarios, salidas (viajes), pagos, deudas, ingresos, egresos y operaciones de caja. La aplicacion esta completamente localizada en espanol (locale: `es`, timezone: `America/Lima`).

### 1.2 Stack Tecnologico

| Componente | Tecnologia |
|---|---|
| Backend | Laravel 11 (PHP) |
| Frontend Reactivo | Livewire 3 |
| RBAC | Spatie Laravel Permission |
| CSS | Tailwind CSS 3 |
| Build Tool | Vite 5 |
| UI Adicional | jQuery UI Datepicker (locale ES), SweetAlert |
| Exportacion | Maatwebsite Excel 3.1 (26 clases de export) |
| Base de Datos | MySQL |
| Autenticacion | Laravel Auth nativo (por username) |
| Auditoria | Trait personalizado `Auditable` |

### 1.3 Arquitectura y Flujo de Requests

```
Navegador
    |
    v
routes/web.php  -->  Controllers (thin)  -->  Blade Views
                                                   |
                                                   v
                                           Componentes Livewire
                                                   |
                                                   v
                                           Models / DB / Services
```

- **Controllers**: Son delgados (thin controllers). Renderizan vistas y manejan descargas de Excel.
- **Logica de negocio**: Reside en componentes Livewire y en `app/Services/`.
- **Exportaciones**: Cada ruta de export mapea a una clase dedicada en `app/Exports/`.
- **Auditoria**: El trait `Auditable` registra automaticamente created/updated/deleted en `activity_logs`.

### 1.4 Patron Livewire

Todos los componentes Livewire siguen este patron:

- Validacion via `public function rules()` o propiedad `$rules`.
- Comunicacion via `$this->dispatch('eventName', [...])` y atributo `#[On('eventName')]`.
- Alertas: `$this->dispatch('successAlert', ['message' => '...'])`.
- Filtros sincronizados con URL via atributo `#[Url]`.

---

## 2. BASE DE DATOS

### 2.1 Diagrama de Tablas y Relaciones

```
users ----< departures
users ----< payments
users ----< incomes
users ----< debt_days_detail
users >---- headquarters (belongsTo)
users >---< headquarters (belongsToMany via headquarter_user)

owners ----< vehicles
drivers ----< vehicles

vehicles ----< departures
vehicles ----< payments
vehicles ----< cost_per_plates
vehicles ----< cost_per_plate_days
vehicles ----< debt_days

debt_days ----< debt_days_detail

departures >---- headquarters
payments >---- headquarters
expenses >---- headquarters
```

### 2.2 Tabla: `users`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | Auto-increment |
| name | varchar | No | Nombre completo |
| username | varchar | No | Nombre de usuario (login) |
| email | varchar | Si | Correo electronico |
| password | varchar | No | Hash bcrypt |
| document_type | varchar | Si | Tipo de documento |
| document_number | varchar | Si | Numero de documento |
| phone | varchar | Si | Telefono |
| headquarter_id | FK | Si | Sucursal principal |
| status | varchar | No | 'active' / 'inactive' |
| sort_order | integer | Si | Orden de visualizacion |
| email_verified_at | timestamp | Si | |
| remember_token | varchar | Si | |
| created_at / updated_at | timestamps | | |

**Tabla pivot:** `headquarter_user` (relacion N:N con headquarters, con campo `is_default`)

### 2.3 Tabla: `vehicles`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| sort_order | integer | Si | Orden de visualizacion |
| plate | varchar(15) | No | Placa, UNIQUE. Setter normaliza a mayusculas |
| headquarters | varchar(150) | Si | Nombre de sede (texto libre) |
| entry_date | date | Si | Fecha de ingreso |
| termination_date | date | Si | Fecha de cese |
| class | varchar(50) | Si | Categoria del vehiculo |
| brand | varchar(100) | Si | Marca |
| year | smallint unsigned | Si | Ano de fabricacion |
| model | varchar(100) | Si | Modelo |
| bodywork | varchar(100) | Si | Carroceria |
| color | varchar(50) | Si | Color |
| type | varchar(50) | Si | Modalidad |
| affiliated_company | varchar(150) | Si | Empresa asociada |
| condition | varchar(100) | Si | Condicion: GN, DT, EX, EX5 |
| owner_id | FK -> owners | Si | Propietario |
| driver_id | FK -> drivers | Si | Conductor |
| fuel | varchar(50) | Si | Tipo de combustible |
| soat_date | date | Si | Vencimiento SOAT |
| certificate_date | date | Si | Vencimiento certificado |
| technical_review | date | Si | Vencimiento revision tecnica |
| detail | text | Si | Detalles adicionales |
| validity_status | varchar(20) | Si | 'valid' / 'expired' |
| status | varchar(50) | No | 'active' / 'inactive' (default: active) |
| seats | integer | Si | Cantidad de asientos |
| passengers | integer | Si | Capacidad de pasajeros |

**Indices:** `(owner_id, driver_id)`, `status`, `validity_status`, UNIQUE en `plate`.

### 2.4 Tabla: `drivers`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| name | varchar(150) | No | Nombre completo |
| document_number | varchar(50) | No | UNIQUE |
| document_expiration_date | date | Si | Vencimiento de documento |
| birthdate | date | Si | Fecha de nacimiento |
| email | varchar(150) | Si | |
| district | varchar(100) | Si | |
| address | varchar(255) | Si | |
| phone | varchar(50) | Si | |
| license | varchar(50) | Si | Numero de licencia |
| class | varchar(50) | Si | Clase de licencia |
| category | varchar(50) | Si | Categoria de licencia |
| license_issue_date | date | Si | Fecha emision licencia |
| license_revalidation_date | date | Si | Fecha revalidacion |
| contract_start | date | Si | Inicio de contrato |
| contract_end | date | Si | Fin de contrato |
| condition | varchar(100) | Si | |
| status | varchar(50) | No | Default: 'active' |
| road_education | date | Si | Educacion vial |
| road_education_expiration_date | date | Si | |
| road_education_municipality | varchar(150) | Si | |
| credential | date | Si | |
| credential_expiration_date | date | Si | |
| credential_municipality | varchar(150) | Si | |
| score | integer | No | Default: 0 |
| details | text | Si | (agregado en migracion posterior) |
| image_path | varchar | Si | Ruta de imagen (agregado posterior) |

### 2.5 Tabla: `owners`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| name | varchar(150) | No | |
| document_type | varchar(20) | No | DNI, RUC, CE, PASS |
| document_number | varchar(50) | No | |
| document_expiration_date | date | Si | |
| birthdate | date | Si | (agregado en migracion posterior) |
| address | varchar(255) | Si | |
| district | varchar(100) | Si | |
| phone | varchar(50) | Si | |
| email | varchar(150) | Si | |
| status | varchar(50) | No | Default: 'active' |

**Indices:** UNIQUE en `(document_type, document_number)`, INDEX en `name`.

### 2.6 Tabla: `departures`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| date | date | No | Fecha de la salida |
| hour | time | No | Hora de la salida |
| vehicle_id | FK -> vehicles | Si | Vehiculo asociado |
| times | smallint unsigned | No | Cantidad de vueltas (default: 1) |
| user_id | FK -> users | Si | Controlador que registro |
| headquarter_id | FK -> headquarters | Si | Sucursal/paradero |
| price | decimal(10,2) | Si | Tarifa total |
| passage | decimal(10,2) | Si | Pasaje |
| latitude | decimal(10,7) | Si | Geolocalizacion |
| longitude | decimal(10,7) | Si | |
| passenger | smallint unsigned | Si | Cantidad de pasajeros |
| is_support | boolean | No | Default: false. Marca si es apoyo |
| legacy_plate | varchar(32) | Si | Placa legacy (para apoyos) |

**Indices:** `(date, headquarter_id)`, `(vehicle_id, date)`, INDEX en `is_support`, `legacy_plate`.

### 2.7 Tabla: `payments`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| serie | varchar | Si | Numero de serie |
| date_register | date | Si | Fecha de registro en caja |
| date_payment | date | Si | Fecha real de pago |
| vehicle_id | FK -> vehicles | Si | |
| amount | decimal(10,2) | Si | Monto del pago |
| type | varchar(50) | Si | PAGO, DEUDA, RETRASO |
| user_id | FK -> users | Si | |
| headquarter_id | FK -> headquarters | Si | |
| hour | time | Si | |
| latitude | varchar(32) | Si | |
| longitude | varchar(32) | Si | |
| legacy_plate | varchar(32) | Si | |
| is_support | boolean | No | Default: false |

**Indices:** `date_register`, `date_payment`, `legacy_plate`, `is_support`.

### 2.8 Tabla: `incomes`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | NO auto-incremental (ID legacy) |
| date | date | No | Fecha de ingreso |
| reason | varchar(191) | Si | Concepto/razon |
| detail | text | Si | Detalle |
| image_path | varchar | Si | Ruta de imagen adjunta |
| total | decimal(12,2) | No | Monto total (default: 0) |
| user_id | FK -> users | Si | Usuario que registro |

### 2.9 Tabla: `expenses`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | NO auto-incremental (ID legacy) |
| date | date | Si | |
| reason | varchar(150) | Si | Concepto |
| detail | text | Si | Detalle |
| image_path | varchar | Si | Ruta de imagen adjunta |
| total | decimal(10,2) | No | Default: 0 |
| user_id | FK -> users | Si | |
| headquarter_id | FK -> headquarters | Si | |
| document_type | varchar(50) | Si | Tipo de comprobante |
| in_charge | varchar(100) | Si | Responsable |

### 2.10 Tabla: `debt_days`

Almacena la deuda mensual por vehiculo. Cada fila representa un vehiculo en un mes.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| vehicle_id | FK -> vehicles | Si | |
| legacy_plate | varchar(20) | Si | |
| is_support | boolean | No | Default: false |
| d1..d31 | varchar(8) | Si | Cada dia: 'X' (sin pago), 'X1' (sin pago con salida), null, 'P' |
| days | smallint unsigned | No | Dias con deuda |
| total | decimal(12,2) | No | Monto total de deuda |
| date | date | No | Primer dia del mes (YYYY-MM-01) |
| exonerated | decimal(12,2) | No | Monto exonerado |
| detail_exonerated | varchar(255) | Si | Detalle de exoneracion |
| amortized | decimal(12,2) | No | Monto amortizado |
| condition | varchar(10) | Si | DT, GN, EX, EX5 |
| days_late | smallint unsigned | No | Dias de atraso |

**Restriccion UNIQUE:** `(date, vehicle_id, legacy_plate)`.

### 2.11 Tabla: `debt_days_detail`

Detalles de exoneraciones y amortizaciones por registro de deuda.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| debt_days_id | FK -> debt_days | No | Restrict on delete |
| exonerated | decimal(10,2) | No | Default: 0 |
| amortized | decimal(10,2) | No | Default: 0 |
| detail | varchar(255) | Si | |
| user_id | FK -> users | Si | |
| date | date | Si | |

### 2.12 Tabla: `cost_per_plates`

Costo mensual por vehiculo/placa.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| vehicle_id | FK -> vehicles | No | CASCADE on delete |
| year | year | No | |
| month | tinyint unsigned | No | |
| amount | decimal(10,2) | No | Monto mensual |
| order | integer | Si | Orden de visualizacion |

### 2.13 Tabla: `cost_per_plate_days`

Costo diario por vehiculo.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| vehicle_id | bigint unsigned | No | |
| year | smallint unsigned | No | |
| month | tinyint unsigned | No | |
| date | date | No | |
| amount | decimal(10,2) | No | Default: 0 |
| headquarter_id | FK | Si | (opcional) |

**Restriccion UNIQUE:** `(vehicle_id, date)`.

### 2.14 Tabla: `headquarters`

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| name | varchar(150) | No | |
| sort_order | integer | Si | Orden de visualizacion |
| status | enum('active','inactive') | No | Default: 'active' |

### 2.15 Tabla: `concepts`

Categorias predefinidas de ingresos/egresos.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| code | varchar | Si | Codigo del concepto |
| name | varchar | No | Nombre |
| type | varchar(50) | No | Tipo |
| status | varchar(20) | No | Default: 'active' |

**Restriccion UNIQUE:** `(name, type)`.

### 2.16 Tabla: `activity_logs`

Registro de auditoria automatico.

| Columna | Tipo | Nullable | Descripcion |
|---|---|---|---|
| id | bigint (PK) | No | |
| user_id | FK -> users | Si | |
| user_name | varchar | Si | Snapshot del nombre |
| user_role | varchar | Si | Snapshot del rol |
| action | varchar(20) | No | 'created', 'updated', 'deleted' |
| module | varchar(100) | No | Nombre del modulo |
| record_id | bigint unsigned | Si | ID del registro afectado |
| old_data | JSON | Si | Datos antes del cambio |
| new_data | JSON | Si | Datos despues del cambio |
| changed_fields | JSON | Si | Lista de campos modificados |
| ip_address | varchar(45) | Si | |
| user_agent | text | Si | |
| created_at | timestamp | No | |

### 2.17 Tabla: `headquarter_user` (pivot)

| Columna | Tipo | Descripcion |
|---|---|---|
| headquarter_id | FK | |
| user_id | FK | |
| is_default | boolean | Si es la sede por defecto |
| created_at / updated_at | timestamps | |

### 2.18 Tablas de Spatie Permission

- `permissions` (extendida con: module, module_label, label, description)
- `roles`
- `role_has_permissions`
- `model_has_permissions`
- `model_has_roles`

### 2.19 Tablas del Framework

- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`

---

## 3. MODELOS

### 3.1 Vehicle (`app/Models/Vehicle.php`)

**Traits:** `Auditable` (modulo: 'Vehiculos')

**Fillable:** `sort_order`, `plate`, `headquarters`, `entry_date`, `termination_date`, `class`, `brand`, `year`, `model`, `bodywork`, `color`, `type`, `affiliated_company`, `condition`, `owner_id`, `driver_id`, `fuel`, `soat_date`, `certificate_date`, `technical_review`, `detail`, `validity_status`, `status`, `seats`, `passengers`

**Casts:**

| Atributo | Tipo |
|---|---|
| order | integer |
| year | integer |
| entry_date | date |
| termination_date | date |
| soat_date | date |
| certificate_date | date |
| technical_review | date |

**Relaciones:**

| Metodo | Tipo | Modelo Relacionado |
|---|---|---|
| `owner()` | BelongsTo | Owner (withDefault) |
| `driver()` | BelongsTo | Driver (withDefault) |
| `costs()` | HasMany | CostPerPlate |
| `departures()` | HasMany | Departure |
| `payments()` | HasMany | Payment |
| `debtDays()` | HasMany | DebtDay |

**Scopes:**

- `scopeActive($q)`: Filtra `status = 'active'`
- `scopeByCondition($q, ?string $cond)`: Filtra por condition
- `scopeByPlate($q, string $term)`: Busca por placa (LIKE, case-insensitive)

**Metodos Especiales:**

- `setPlateAttribute($value)`: Mutador que normaliza la placa a mayusculas, elimina caracteres no alfanumericos (excepto guiones y espacios).
- `plateKey()`: Retorna clave de comparacion sin espacios/guiones.
- `getBadgesAttribute()`: Accessor que retorna array de badges con estado de vencimiento de SOAT (SD), Revision Tecnica (RT) y Certificado (CD). Colores: `bg-danger` (vencido, hoy, o <=5 dias), `bg-warning` (6-10 dias).
- `expiringAlerts()`: Similar a badges pero con estructura mas detallada para notificaciones globales.
- `dayDelta($date)`: Calcula diferencia en dias entre hoy y una fecha. Retorna: negativo = vencido, 0 = hoy, positivo = faltan N dias.

### 3.2 Driver (`app/Models/Driver.php`)

**Traits:** `Auditable` (modulo: 'Conductores')

**Fillable:** `name`, `document_number`, `document_expiration_date`, `birthdate`, `email`, `district`, `address`, `phone`, `license`, `class`, `category`, `license_issue_date`, `license_revalidation_date`, `contract_start`, `contract_end`, `condition`, `status`, `road_education`, `road_education_expiration_date`, `road_education_municipality`, `credential`, `credential_expiration_date`, `credential_municipality`, `score`, `details`, `image_path`

**Casts:** 10 campos date (document_expiration_date, birthdate, license_issue_date, license_revalidation_date, contract_start, contract_end, road_education, road_education_expiration_date, credential, credential_expiration_date).

**Relaciones:** `vehicles()` -> HasMany Vehicle.

### 3.3 Owner (`app/Models/Owner.php`)

**Traits:** `Auditable` (modulo: 'Propietarios')

**Fillable:** `name`, `document_type`, `document_number`, `document_expiration_date`, `birthdate`, `address`, `district`, `phone`, `email`, `status`

**Casts:** `document_expiration_date` -> date, `birthdate` -> date.

**Relaciones:** `vehicles()` -> HasMany Vehicle.

### 3.4 Departure (`app/Models/Departure.php`)

**Traits:** `Auditable` (modulo: 'Salidas')

**Fillable:** `date`, `hour`, `vehicle_id`, `times`, `user_id`, `headquarter_id`, `price`, `latitude`, `longitude`, `passenger`, `passage`, `is_support`, `legacy_plate`

**Casts:** `date` -> date, `hour` -> datetime:H:i:s, `price` -> decimal:2, `passage` -> decimal:2, `latitude` -> decimal:7, `longitude` -> decimal:7, `times` -> integer, `passenger` -> integer.

**Relaciones:** `vehicle()` -> BelongsTo Vehicle, `user()` -> BelongsTo User, `headquarter()` -> BelongsTo Headquarter.

**Scopes:**

- `scopeBetweenDates($q, $from, $to)`: Filtra por rango de `date`.
- `scopeSupport($q)`: Solo registros de apoyo (`is_support = true`).
- `scopeExcludeHQ($q, array $names)`: Excluye sedes por nombre (default: Huachipa, Lima).

**Metodos de permisos:**

- `canBeEditedBy(User $user)`: Director siempre. Admin/gerente solo si fue creado hoy.
- `canBeDeletedBy(User $user)`: Misma logica.

### 3.5 Payment (`app/Models/Payment.php`)

**Traits:** `Auditable` (modulo: 'Pagos')

**Fillable:** `id`, `serie`, `date_register`, `date_payment`, `vehicle_id`, `amount`, `type`, `user_id`, `headquarter_id`, `hour`, `latitude`, `longitude`, `legacy_plate`, `is_support`

**Casts:** `date_register` -> date, `date_payment` -> date, `amount` -> decimal:2.

**Relaciones:** `vehicle()`, `user()`, `headquarter()`.

**Scopes:**

- `scopeBetweenRegister($q, $from, $to)`: Filtra por `date_register`.
- `scopeBetweenPayment($q, $from, $to)`: Filtra por `date_payment`.
- `scopeByType($q, ?string $type)`: Filtra por tipo de pago.
- `scopeByHeadquarter($q, $headquarterId)`: Filtra por sucursal.
- `scopeSearchPlate($q, $term)`: Busca por placa (legacy o vehicle.plate).
- `scopeSearchUserName($q, $term)`: Busca por nombre de usuario.
- `scopeSearchSerie($q, $term)`: Busca por serie.

**Metodos de permisos:** `canBeEditedBy()`, `canBeDeletedBy()` - misma logica que Departure.

### 3.6 Income (`app/Models/Income.php`)

**Traits:** `Auditable` (modulo: 'Ingresos')

**Nota:** `$incrementing = false`, `$keyType = 'int'` (usa ID legacy).

**Fillable:** `id`, `date`, `reason`, `detail`, `image_path`, `total`, `user_id`

**Casts:** `date` -> date, `total` -> decimal:2.

**Accessor:** `getImageUrlAttribute()` - Retorna URL de imagen o placeholder.

**Relaciones:** `user()` -> BelongsTo User.

### 3.7 Expense (`app/Models/Expense.php`)

**Traits:** `Auditable` (modulo: 'Egresos')

**Nota:** `$incrementing = false`, `$keyType = 'int'` (usa ID legacy).

**Fillable:** `id`, `date`, `reason`, `detail`, `image_path`, `total`, `user_id`, `headquarter_id`, `document_type`, `in_charge`

**Casts:** `date` -> date, `total` -> decimal:2.

**Accessor:** `getImageUrlAttribute()`.

**Relaciones:** `user()`, `headquarter()`.

### 3.8 DebtDay (`app/Models/DebtDay.php`)

**Traits:** `Auditable` (modulo: 'Deuda por Dias')

**Fillable:** `vehicle_id`, `legacy_plate`, `is_support`, `d1`..`d31`, `days`, `total`, `date`, `exonerated`, `detail_exonerated`, `amortized`, `condition`, `days_late`

**Casts:** `date` -> date, `is_support` -> boolean, `exonerated` -> decimal:2, `total` -> decimal:2, `amortized` -> decimal:2.

**Relaciones:** `vehicle()` -> BelongsTo Vehicle, `details()` -> HasMany DebtDayDetail.

### 3.9 DebtDayDetail (`app/Models/DebtDayDetail.php`)

**Traits:** `Auditable` (modulo: 'Detalle Deuda')

**Nota:** `$incrementing = false` (PK legado).

**Fillable:** `id`, `debt_days_id`, `exonerated`, `amortized`, `detail`, `user_id`, `date`

**Relaciones:** `debtDay()` -> BelongsTo DebtDay, `user()` -> BelongsTo User.

### 3.10 CostPerPlate (`app/Models/CostPerPlate.php`)

**Traits:** `Auditable`

**Fillable:** `vehicle_id`, `year`, `month`, `amount`, `order`

**Relaciones:** `vehicle()` -> BelongsTo Vehicle.

### 3.11 CostPerPlateDay (`app/Models/CostPerPlateDay.php`)

**Traits:** `Auditable`

**Casts:** `amount` -> decimal:2, `date` -> date, `year` -> integer, `month` -> integer.

**Relaciones:** `headquarter()`, `vehicle()` (withDefault).

### 3.12 User (`app/Models/User.php`)

**Traits:** `HasRoles` (Spatie), `Auditable` (modulo: 'Usuarios'), `HasFactory`, `Notifiable`.

**Constante ROLE_HIERARCHY:**

```php
const ROLE_HIERARCHY = [
    'controlador'   => 1,
    'supervisor'    => 2,
    'administrador' => 3,
    'gerente'       => 4,
    'director'      => 5,
];
```

**Fillable:** `name`, `username`, `email`, `password`, `document_type`, `document_number`, `phone`, `headquarter_id`, `status`, `sort_order`

**Hidden:** `password`, `remember_token`.

**auditExclude:** `password`, `remember_token`.

**Relaciones:**

| Metodo | Tipo | Detalle |
|---|---|---|
| `headquarter()` | BelongsTo | Sucursal principal |
| `headquarters()` | BelongsToMany | Multiples sucursales (pivot con `is_default`) |
| `payments()` | HasMany | Pagos registrados |
| `departures()` | HasMany | Salidas registradas |
| `debtDayDetails()` | HasMany | Detalles de deuda |
| `incomes()` | HasMany | Ingresos registrados |

**Metodos:**

- `getAvatarUrlAttribute()`: Genera avatar via ui-avatars.com.
- `getRoleLevel()`: Retorna nivel numerico del rol (1-5).
- `isDirector()`: Verifica si tiene rol 'director'.
- `canManageUser(User $target)`: Director puede gestionar usuarios de igual o menor nivel; otros solo menor nivel.

### 3.13 Headquarter (`app/Models/Headquarter.php`)

**Traits:** `Auditable`

**Fillable:** `name`, `sort_order`, `status`

**Relaciones:** `users()` -> HasMany User, `activeUsers()` -> BelongsToMany (solo usuarios activos con rol controlador), `departures()`, `payments()`.

### 3.14 Concept (`app/Models/Concept.php`)

**Traits:** `Auditable`

**Fillable:** `code`, `name`, `type`, `status`

### 3.15 ActivityLog (`app/Models/ActivityLog.php`)

**timestamps:** Deshabilitado (`$timestamps = false`), tiene solo `created_at`.

**Fillable:** `user_id`, `user_name`, `user_role`, `action`, `module`, `record_id`, `old_data`, `new_data`, `changed_fields`, `ip_address`, `user_agent`, `created_at`

**Scopes:** `byModule()`, `byUser()`, `byAction()`, `byDateRange()`.

### 3.16 Permission (`app/Models/Permission.php`)

Extiende el modelo base de Eloquent (no el de Spatie directamente).

**Fillable:** `name`, `guard_name`, `module`, `module_label`, `label`, `description`

---

## 4. CONTROLADORES

Todos los controllers son **thin** (delgados): renderizan vistas Blade y manejan exportaciones a Excel. La logica de negocio reside en los componentes Livewire.

### 4.1 DashboardController

**Archivo:** `app/Http/Controllers/DashboardController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /dashboard | auth, permission:dashboard | Renderiza `dashboard.index` |

### 4.2 VehicleController

**Archivo:** `app/Http/Controllers/VehicleController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /vehicles | auth, permission:configuracion.vehicles | Lista de vehiculos |
| `create()` | GET /vehicles/create | auth, role:director\|gerente\|administrador | Crear vehiculo |
| `edit($id)` | GET /vehicles/{id}/edit | auth, role:director\|gerente\|administrador | Editar vehiculo |
| `export()` | GET /exports/vehicles | auth, permission:configuracion.vehicles | Exporta vehiculos a XLS |

### 4.3 OwnerController

**Archivo:** `app/Http/Controllers/OwnerController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /owners | auth, permission:configuracion.owners | Lista |
| `create()` | GET /owners/create | auth, role:director\|gerente\|administrador | Crear |
| `edit($id)` | GET /owners/{id}/edit | auth, role:director\|gerente\|administrador | Editar |
| `export()` | GET /exports/owners | auth | Exporta XLS |

### 4.4 DriverController

**Archivo:** `app/Http/Controllers/DriverController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /drivers | auth, permission:configuracion.drivers | Lista |
| `create()` | GET /drivers/create | auth, role:director\|gerente\|administrador | Crear |
| `edit($id)` | GET /drivers/{id}/edit | auth, role:director\|gerente\|administrador | Editar |
| `export()` | GET /exports/drivers | auth | Exporta XLS |

### 4.5 CostPerPlateController

**Archivo:** `app/Http/Controllers/CostPerPlateController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /cost-per-plate | auth, permission:configuracion.cost-per-plate | Listado mensual |
| `day($year,$month)` | GET /cost-per-plate/day/{year}/{month} | auth, permission:configuracion.cost-per-plate | Detalle diario |
| `calendar($plate,$year,$month)` | GET /cost-per-plate/calendar/{plate}/{year}/{month} | auth, permission:configuracion.cost-per-plate | Calendario por placa |

### 4.6 DebtController

**Archivo:** `app/Http/Controllers/DebtController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `debtPerDays()` | GET /debts-per-days | auth, permission:debts.days | Deuda por dias |
| `monthly()` | GET /monthly-debt | auth, permission:debts.monthly | Deuda mensual |
| `monthlyDetail($id)` | GET /monthly-debt/{id} | auth | Detalle de deuda |
| `generate()` | GET /debt-generate | auth | Vista de generacion (TODO: eliminar) |
| `delete()` | GET /delete-debt | auth | Vista de eliminacion (TODO: eliminar) |
| `export()` | GET /exports/debts-per-days | auth, permission:debts.days | Exporta deuda por dias |
| `exportDetail()` | GET /exports/debts-per-days-detail | auth, permission:debts.days | Exporta detalle de retrasos |
| `exportMonthly()` | GET /exports/debts-monthly | auth, permission:debts.monthly | Exporta deuda mensual |

### 4.7 UserController

**Archivo:** `app/Http/Controllers/UserController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /users | auth, permission:configuracion.users | Lista |
| `create()` | GET /users/create | auth, role:director | Crear |
| `edit($id)` | GET /users/{user}/edit | auth, permission:configuracion.users | Editar |
| `perms($id)` | GET /users/{user}/perms | auth, role:director\|gerente\|administrador | Asignar roles/permisos |
| `export()` | GET /exports/users | auth, permission:configuracion.users | Exporta XLS |

### 4.8 ConceptController

**Archivo:** `app/Http/Controllers/ConceptController.php`

Resource controller (CRUD). Middleware `permission:configuracion.concepts` + `role:director` para create/edit.

### 4.9 HeadquarterController

**Archivo:** `app/Http/Controllers/HeadquarterController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /headquarters | auth, permission:configuracion.headquarters | Lista |
| `create()` | GET /headquarters/create | auth, role:director\|gerente\|administrador | Crear |
| `edit($id)` | GET /headquarters/{id}/edit | auth, role:director | Editar |
| `export()` | GET /exports/headquarters | auth, permission:configuracion.headquarters | Exporta XLS |

### 4.10 DepartureController

**Archivo:** `app/Http/Controllers/DepartureController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /departures | auth, permission:departures | Listado de salidas |
| `add()` | GET /departures/add | auth, permission:departures | Agregar salida |
| `edit($id)` | GET /departures/edit/{id} | auth, role:director\|gerente\|administrador | Editar salida |
| `monthly()` | GET /departures/monthly | auth, role:director\|gerente\|administrador | Reporte mensual |
| `rmp()` | GET /departures/rmp | auth, role:director\|gerente\|administrador | Reporte mensual por paradero |
| `stats()` | GET /departures/stats | auth, role:director\|gerente\|administrador | Estadistico |
| `byDebt()` | GET /departures/by-debt | auth, permission:departures | Salidas por deuda |
| `export()` | GET /exports/departures | auth, permission:departures | Exporta XLS |
| `exportMonthly()` | GET /exports/departures-monthly-export | auth | Exporta mensual XLS |
| `exportRmp()` | GET /exports/departures-rmp-report | auth | Exporta RMP XLS |
| `exportStats()` | GET /exports/departures-stats-report | auth | Exporta estadistico XLS |

### 4.11 PaymentController

**Archivo:** `app/Http/Controllers/PaymentController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `index()` | GET /payments | auth, permission:payments | Listado |
| `add()` | GET /payments/add | auth, permission:payments | Agregar |
| `edit($id)` | GET /payments/edit/{id} | auth, role:director\|gerente\|administrador | Editar |
| `daily()` | GET /payments/daily | auth, role:director\|gerente\|administrador | Reporte diario |
| `monthly()` | GET /payments/monthly | auth, role:director\|gerente\|administrador | Reporte mensual |
| `stats()` | GET /payments/stats | auth, role:director\|gerente\|administrador | Estadistico |
| `export()` | GET /exports/payments | auth, permission:payments | Exporta XLS |
| `exportMonthly()` | GET /exports/payments-monthly | auth | Exporta mensual XLSX |
| `exportDaily()` | GET /exports/payments-daily | auth | Exporta diario XLS |
| `exportStats()` | GET /exports/payments-stats | auth | Exporta estadistico XLS |

### 4.12 CashController

**Archivo:** `app/Http/Controllers/CashController.php`

| Metodo | Ruta | Middleware | Descripcion |
|---|---|---|---|
| `incomes()` | GET /cash/incomes | auth, permission:cash.incomes | Lista ingresos |
| `createIncome()` | GET /cash/incomes/create | auth, permission:cash.incomes | Crear ingreso |
| `editIncome($id)` | GET /cash/incomes/{id}/edit | auth, permission:cash.incomes | Editar ingreso |
| `expenses()` | GET /cash/expenses | auth, permission:cash.expenses | Lista egresos |
| `createExpense()` | GET /cash/expenses/create | auth, permission:cash.expenses | Crear egreso |
| `editExpense($id)` | GET /cash/expenses/{id}/edit | auth, permission:cash.expenses | Editar egreso |
| `generalReport()` | GET /cash/report/general | auth, permission:cash.report-general | Reporte general |
| `reportEstDracoBase()` | GET /cash/report/est-draco-base | auth, permission:cash.report-draco | Reporte Draco Base |
| `reportEstSalPagCont()` | GET /cash/report/est-sal-pag-cont | auth, permission:cash.report-sal-pag-cont | Reporte Sal/Pag Cont |
| `reportEstCajaMa()` | GET /cash/report/est-caja-ma | auth, permission:cash.report-caja-ma | Reporte Caja M.A |
| `exportIncomes()` | GET /exports/incomes | auth, permission:cash.incomes | Exporta XLS |
| `exportExpenses()` | GET /exports/expenses | auth, permission:cash.expenses | Exporta XLS |
| `exportGeneralReport()` | GET /exports/cash-general-report | auth | Exporta XLSX |
| `exportDracoReport()` | GET /exports/cash-draco-report | auth | Exporta XLSX |

### 4.13 DspController

**Archivo:** `app/Http/Controllers/DspController.php`

Controller con vista marcada como TODO para eliminar. Contiene solo `index()`.

---

## 5. COMPONENTES LIVEWIRE

### 5.1 Autenticacion

#### Auth/Login (`app/Livewire/Auth/Login.php`)

**Propiedades publicas:** `username`, `password`, `remember`

**Logica:**
- Autenticacion por `username` (no email).
- Rate limiting: 5 intentos por username+IP, con ventana de 60 segundos.
- Redireccion post-login a `/departures`.

#### Auth/Logout (`app/Livewire/Auth/Logout.php`)

Cierra sesion y redirige al login. Usa evento `#[On('logout')]`.

### 5.2 Dashboard

#### Dashboard/Index (`app/Livewire/Dashboard/Index.php`)

**Propiedades publicas:** `year`, `month`

**Datos calculados:**
- Ingresos del mes (payments + departures + incomes).
- Egresos del mes (expenses).
- Ingresos y egresos del dia.
- Top 5 sucursales por ingresos.
- Top 3 tipos de pago.
- Balances diarios (4 consultas, sin N+1).
- Grafico de datos via evento `chart-data`.

### 5.3 Vehiculos

#### Vehicles/Index

**Filtros (URL-synced):** `search`, `filter` (plate/brand/year/owner/driver/condition/company/category/code), `status` (active/inactive).

**Logica:**
- Listado con filtros dinamicos.
- Vehiculos activos: status='active' y sin termination_date pasada.
- Vehiculos inactivos: status='inactive' o con termination_date pasada.
- Conteo de propietarios y conductores distintos activos.
- Apertura de ventanas create/edit via evento `url-open`.

#### Vehicles/Create

**Validacion:** Placa unica (unique:vehicles,plate), minimo 6 caracteres. Condicion requerida.

**Defaults:** Fechas del dia actual, termination_date = '0000-00-00'.

#### Vehicles/Edit

Similar a Create pero con carga de datos existentes. Permite eliminacion logica (cambio de status a 'inactive'). Solo director/gerente/administrador pueden eliminar.

### 5.4 Salidas (Departures)

#### Departures/Index

Listado paginado de salidas con filtros por: texto de busqueda (placa, usuario, hora, sede), rango de fechas, sucursal, y modo de agrupacion. Soporta CRUD con modal para crear/editar.

**Control de acceso por sedes:** Usuarios no-admin solo ven las sucursales asignadas (via pivot `headquarter_user` + `headquarter_id` principal).

#### Departures/AddDeparture

Formulario de creacion de salida con:
- Resolucion automatica de vehiculo por placa (vehiculo existente activo = normal, inexistente/inactivo = apoyo/support).
- Validacion de geolocalizacion (requiere latitud/longitud).
- Control de acceso a sucursales segun rol.
- Seleccion de sede primaria por defecto.

#### Departures/EditDeparture

Similar a AddDeparture pero para edicion. Incluye eliminacion con confirmacion. Solo admin puede eliminar.

#### Departures/Monthly

Reporte mensual de salidas: grilla de vehiculos x dias del mes.
- Cada celda muestra cantidad de vueltas (raw / 2, redondeado).
- Totales por dia y por vehiculo.
- Seccion separada por sede para apoyos (support).
- Vehiculos trabajados por dia.

#### Departures/Rmp (Reporte Mensual por Paradero)

Reporte complejo con SQL CTE recursiva que calcula:
- Vehiculos de empresa (TE): existen en tabla `vehicles`.
- Vehiculos de apoyo (TA): no existen en `vehicles`.
- Desglose por controlador y paradero, por dia.
- Totales TE, TA y VT (total).

#### Departures/Stats (Estadistico)

Reporte estadistico mensual con SQL CTE que calcula:
- Salidas (`SUM(times)`) por controlador/paradero/dia.
- Montos (`SUM(price)`) por controlador/paradero/dia.
- Dos filas por cada combinacion: "Salidas" y "S/" (soles).

#### Departures/ByDebt

Consulta de salidas por placa y fecha. Agrupa por usuario + sucursal mostrando conteo de salidas.

### 5.5 Pagos (Payments)

#### Payments/Index

Listado paginado con filtros (URL-synced): busqueda, filtro (placa/usuario/serie), rango de fechas, sucursal, tipo de pago. CRUD con formulario.

#### Payments/AddPayment

Formulario para agregar pago con resolucion de vehiculo por placa, control de acceso por sucursal, geolocalizacion.

#### Payments/EditPayment

Edicion de pago existente con las mismas validaciones.

#### Payments/Monthly

Reporte mensual de pagos por vehiculo/dia (similar a Departures/Monthly).

#### Payments/Daily

Reporte diario de pagos con dos modos: por fecha de registro (Caja) o por fecha de pago (Pago).

#### Payments/Stats

Estadistico mensual de pagos.

### 5.6 Deudas (Debts)

#### Debts/DebtPerDays

**Vista principal de deuda por dias.** Calcula para cada vehiculo y cada dia del mes:
- `P` = Pagado (pago >= costo del dia).
- Numero = Vueltas sin pago (ceil(departures/2)).
- `NT` = Sin pago ni salidas.

**Filtros:** Mes/ano, solo activos, condicion (DT/GN/EX/EX5).

**Logica de calculo:**
1. Obtiene vehiculos segun filtros.
2. Carga costos diarios (`cost_per_plate_days`).
3. Carga pagos diarios (excluyendo tipo DEUDA).
4. Carga salidas diarias (excluyendo sedes Huachipa/Lima).
5. Para cada vehiculo/dia: compara pago vs costo, si pagado muestra P, si no muestra vueltas o NT.
6. Vehiculos con condicion EX (exonerados) no suman a totales.

**Fallback de costo:** Si fecha <= 2023-04-30 y no hay costo registrado, usa 10.00.

#### Debts/MonthlyDebt

**Deuda mensual consolidada.** Muestra resumen de deuda por vehiculo:
- Dias de atraso, total, exonerado, a pagar, amortizado, pendiente.
- Filtros: mes/ano, busqueda por placa, condicion (DT/GN/EX/EX5/Exonerado/Amortizado).
- Dias marcados en formato visual: numeros normales y en azul para X1.
- Click abre detalle (`MonthlyDetail`).

#### Debts/MonthlyDetail

**Detalle de exoneracion/amortizacion.** Para un registro `debt_days` especifico:
- Muestra cabecera (placa, dias, total).
- Permite agregar exoneraciones y amortizaciones (no pueden superar el pendiente).
- Tabla de historial de detalles con posibilidad de eliminar.
- Recalcula totales automaticamente desde `debt_days_detail`.

### 5.7 Caja (Cash)

#### Cash/Incomes

Listado de ingresos con filtros por busqueda, tipo de filtro (razon/detalle), rango de fechas. Paginado.

#### Cash/CreateIncome

Formulario de creacion de ingreso con soporte de imagen adjunta.

#### Cash/EditIncome

Edicion de ingreso. Respeta policies (IncomePolicy).

#### Cash/Expenses

Listado de egresos con filtros.

#### Cash/CreateExpense

Formulario de creacion de egreso con soporte de imagen.

#### Cash/EditExpense

Edicion de egreso. Respeta policies (ExpensePolicy).

#### Cash/GeneralReport

**Reporte general de caja.** Muestra dia por dia del mes:
- Ingresos: payments (por tipo y sucursal), departures (por sucursal), incomes individuales.
- Egresos: expenses individuales.
- Saldo del dia y saldo acumulado progresivo.
- Totales del mes.
- Exporta a Excel.

#### Cash/RepEstDracoBase

Reporte estadistico "Draco Base" con datos anuales.

#### Cash/RepEstPagCont

Reporte estadistico de salidas, pagos y contabilidad.

#### Cash/RepEstCajaMa

Reporte estadistico de caja M.A.

### 5.8 Costo por Placa (CostPerPlate)

#### CostPerPlate/Index

Listado de costos mensuales agrupados por ano/mes. Muestra cantidad de placas y monto minimo. Permite generar costos para un mes a partir del mes anterior.

**Logica de generacion:**
1. Toma el ultimo dia NO domingo del mes anterior por vehiculo para obtener el monto base.
2. Fallback al monto mensual anterior.
3. Genera registros mensuales y diarios (domingos con monto 0).
4. Elimina datos previos del mes destino antes de insertar.

#### CostPerPlate/CostPerPlateDay

Detalle de costos diarios por vehiculo en un mes especifico. Permite edicion de montos individuales.

#### CostPerPlate/Calendar

Vista de calendario de costos para una placa especifica en un mes.

### 5.9 Usuarios (Users)

#### Users/Index

Listado de usuarios con busqueda y estado.

#### Users/Create

Formulario de creacion de usuario con asignacion de rol.

#### Users/Edit

Edicion de datos de usuario.

#### Users/Perms

**Gestion de roles y permisos.** Solo Director puede editar.

**Logica:**
- Muestra ACL agrupado por modulos (sidebar order: dashboard, configuracion, departures, payments, debts, cash).
- Si el usuario editado es Director, se asignan todos los permisos automaticamente y no se permite edicion.
- Al cambiar de rol, se limpian permisos y sucursales.
- Respeta `canManageUser()`: un usuario solo puede gestionar a usuarios de menor nivel jerarquico (excepto Director que puede gestionar iguales).

### 5.10 Propietarios y Conductores

#### Owners/Index, Create, Edit

CRUD basico de propietarios con busqueda y filtros.

#### Drivers/Index, Create, Edit

CRUD basico de conductores con busqueda y filtros.

### 5.11 Sucursales (Headquarters)

#### Headquarters/Index, Create, Edit

CRUD de sucursales. Solo Director puede crear/editar.

### 5.12 Conceptos (Concepts)

#### Concepts/Index, Create, Edit

CRUD de conceptos de ingresos/egresos. Solo Director puede crear/editar.

### 5.13 Auditoria

#### AuditLogs/Index (`app/Livewire/AuditLogs/Index.php`)

**Solo accesible por Director** (middleware `role:director`).

**Filtros:** Modulo, accion (default: 'deleted'), usuario, rango de fechas.

**Funcionalidades:**
- Paginacion (20 registros).
- Modal de detalle que muestra old_data vs new_data.
- Resolucion de vehicle_id a placa para mejor legibilidad.

### 5.14 DSP

#### Dsp/Index

Vista de eliminacion de deudas/salidas/pagos. Marcada como TODO para eliminar.

---

## 6. RUTAS

### 6.1 Rutas Publicas

```
GET  /login     -> Vista de login (middleware: guest)
GET  /           -> Redirect a /login
POST /logout     -> Cierre de sesion (CSRF)
```

### 6.2 Rutas Protegidas (auth)

#### Dashboard
```
GET /dashboard                              -> DashboardController@index
```

#### Configuracion - Vehiculos
```
GET /vehicles                               -> VehicleController@index
GET /vehicles/create                        -> VehicleController@create      [role:director|gerente|administrador]
GET /vehicles/{id}/edit                     -> VehicleController@edit         [role:director|gerente|administrador]
```

#### Configuracion - Propietarios
```
GET /owners                                 -> OwnerController@index
GET /owners/create                          -> OwnerController@create        [role:director|gerente|administrador]
GET /owners/{id}/edit                       -> OwnerController@edit           [role:director|gerente|administrador]
```

#### Configuracion - Conductores
```
GET /drivers                                -> DriverController@index
GET /drivers/create                         -> DriverController@create       [role:director|gerente|administrador]
GET /drivers/{id}/edit                      -> DriverController@edit          [role:director|gerente|administrador]
```

#### Configuracion - Costo por Placa
```
GET /cost-per-plate                         -> CostPerPlateController@index
GET /cost-per-plate/day/{year}/{month}      -> CostPerPlateController@day
GET /cost-per-plate/calendar/{plate}/{year}/{month} -> CostPerPlateController@calendar
```

#### Configuracion - Usuarios
```
GET /users                                  -> UserController@index
GET /users/create                           -> UserController@create          [role:director]
GET /users/{user}/edit                      -> UserController@edit
GET /users/{user}/perms                     -> UserController@perms           [role:director|gerente|administrador]
```

#### Configuracion - Conceptos
```
Resource /concepts                          -> ConceptController (CRUD)
```

#### Configuracion - Sucursales
```
GET /headquarters                           -> HeadquarterController@index
GET /headquarters/create                    -> HeadquarterController@create   [role:director|gerente|administrador]
GET /headquarters/{id}/edit                 -> HeadquarterController@edit     [role:director]
```

#### Salidas
```
GET /departures                             -> DepartureController@index
GET /departures/add                         -> DepartureController@add
GET /departures/edit/{id}                   -> DepartureController@edit       [role:director|gerente|administrador]
GET /departures/monthly                     -> DepartureController@monthly    [role:director|gerente|administrador]
GET /departures/rmp                         -> DepartureController@rmp        [role:director|gerente|administrador]
GET /departures/stats                       -> DepartureController@stats      [role:director|gerente|administrador]
GET /departures/by-debt                     -> DepartureController@byDebt
```

#### Pagos
```
GET /payments                               -> PaymentController@index
GET /payments/add                           -> PaymentController@add
GET /payments/edit/{id}                     -> PaymentController@edit         [role:director|gerente|administrador]
GET /payments/daily                         -> PaymentController@daily        [role:director|gerente|administrador]
GET /payments/monthly                       -> PaymentController@monthly      [role:director|gerente|administrador]
GET /payments/stats                         -> PaymentController@stats        [role:director|gerente|administrador]
```

#### Deudas
```
GET /debts-per-days                         -> DebtController@debtPerDays
GET /monthly-debt                           -> DebtController@monthly
GET /monthly-debt/{id}                      -> DebtController@monthlyDetail
GET /debt-generate                          -> DebtController@generate        (TODO: eliminar)
GET /delete-debt                            -> DebtController@delete           (TODO: eliminar)
```

#### Caja
```
GET /cash/open                              -> CashController@open
GET /cash/report/movement                   -> CashController@movementReport
GET /cash/incomes                           -> CashController@incomes
GET /cash/incomes/create                    -> CashController@createIncome
GET /cash/incomes/{id}/edit                 -> CashController@editIncome
GET /cash/expenses                          -> CashController@expenses
GET /cash/expenses/create                   -> CashController@createExpense
GET /cash/expenses/{id}/edit                -> CashController@editExpense
GET /cash/report/general                    -> CashController@generalReport
GET /cash/report/est-draco-base             -> CashController@reportEstDracoBase
GET /cash/report/est-sal-pag-cont           -> CashController@reportEstSalPagCont
GET /cash/report/est-caja-ma                -> CashController@reportEstCajaMa
```

#### Auditoria
```
GET /audit-logs                             -> Vista audit-logs.index         [role:director]
```

#### Exportaciones (26 rutas GET)
```
GET /exports/vehicles
GET /exports/owners
GET /exports/drivers
GET /exports/departures
GET /exports/incomes
GET /exports/expenses
GET /exports/payments
GET /exports/debts-per-days
GET /exports/debts-per-days-detail
GET /exports/debts-monthly
GET /exports/cash-general-report
GET /exports/cash-draco-report
GET /exports/departures-monthly-export
GET /exports/departures-rmp-report
GET /exports/departures-stats-report
GET /exports/payments-monthly
GET /exports/payments-daily
GET /exports/payments-stats
GET /exports/users
GET /exports/concepts
GET /exports/headquarters
```

---

## 7. VISTAS

### 7.1 Estructura de Directorios

```
resources/views/
  layout/
    css.blade.php           - Estilos CSS
    footer.blade.php        - Footer
    head.blade.php          - Head HTML
    header.blade.php        - Header con alertas de vencimiento de vehiculos
    index.blade.php         - Layout principal
    js.blade.php            - Scripts JS
    sidebar.blade.php       - Barra lateral
  auth/
    index.blade.php         - Pagina de login
  partials/
    quick-access.blade.php  - Accesos rapidos
    sidebar-menu.blade.php  - Menu del sidebar
  components/
    loading-overlay.blade.php - Overlay de carga

  # Vistas de Controllers (renderizan componentes Livewire)
  dashboard/index.blade.php
  vehicles/{index,create,edit}.blade.php
  owners/{index,create,edit}.blade.php
  drivers/{index,create,edit}.blade.php
  departures/{index,add,edit,monthly,rmp,stats,by-debt}.blade.php
  payments/{index,add,edit,daily,monthly,stats}.blade.php
  debts/{debt-per-days,monthly,monthly-detail,generate,delete}.blade.php
  cash/{incomes,expenses,create-income,create-expense,edit-income,edit-expense,
       general-report,report-est-draco-base,rep-est-sal-pag-cont,rep-est-caja-ma,
       open,movement-report}.blade.php
  cost-per-plate/{index,cost-per-plate-day,calendar}.blade.php
  users/{index,create,edit,perms}.blade.php
  concepts/{index,create,edit}.blade.php
  headquarters/{index,create,edit}.blade.php
  audit-logs/index.blade.php

  # Vistas de Componentes Livewire
  livewire/
    auth/{login,logout}.blade.php
    dashboard/index.blade.php
    vehicles/{index,create,edit,_form}.blade.php
    owners/{index,create,edit,_form}.blade.php
    drivers/{index,create,edit}.blade.php
    departures/{index,add-departure,edit-departure,monthly,rmp,stats,by-debt}.blade.php
    payments/{index,add-payment,edit-payment,daily,monthly,stats}.blade.php
    debts/{debt-per-days,monthly-debt,monthly-detail}.blade.php
    cash/{incomes,expenses,create-income,create-expense,edit-income,edit-expense,
         general-report,rep-est-draco-base,rep-est-pag-cont,rep-est-caja-ma}.blade.php
    cost-per-plate/{index,cost-per-plate-day,calendar}.blade.php
    users/{index,create,edit,perms}.blade.php
    concepts/{index,create,edit}.blade.php
    headquarters/{index,create,edit}.blade.php
    audit-logs/index.blade.php
    dsp/index.blade.php

  # Vistas de Exportacion (plantillas HTML para Excel)
  exports/
    vehicles.blade.php
    owners.blade.php
    drivers.blade.php
    departures.blade.php
    departures-monthly.blade.php
    departures-rmp.blade.php
    departures-stats.blade.php
    payments.blade.php
    payments-daily.blade.php
    payments-stats.blade.php
    incomes.blade.php
    expenses.blade.php
    concepts.blade.php
    headquarters.blade.php
    users.blade.php
    debts_per_days_only_grid.blade.php
```

### 7.2 Layout Principal

El layout principal (`layout/index.blade.php`) incluye:
- **Header** (`layout/header.blade.php`): Con alertas de vencimiento de SOAT/RT/CD de vehiculos (cacheadas 60s via View Composer en `AppServiceProvider`).
- **Sidebar** (`layout/sidebar.blade.php`): Menu de navegacion.
- **Footer** (`layout/footer.blade.php`).
- **CSS/JS**: Cargados via Vite.

---

## 8. EXPORTS

El sistema tiene 26 clases de exportacion en `app/Exports/`. Se utilizan dos patrones:

### Patron 1: HTML con header Content-Type XLS

La mayoria de exports generan una tabla HTML que se descarga con header `Content-Type: application/vnd.ms-excel`. Esto produce archivos `.xls` que Excel abre como tablas.

### Patron 2: Maatwebsite Excel (XLSX real)

Algunos exports usan `Excel::download()` para generar archivos XLSX reales.

### Lista de Exports

| Clase | Formato | Descripcion |
|---|---|---|
| `VehiclesReportExport` | XLS/HTML | Reporte de vehiculos filtrado |
| `OwnersReportExport` | XLS/HTML | Reporte de propietarios |
| `DriversReportExport` | XLS/HTML | Reporte de conductores |
| `DeparturesExport` | XLS/HTML | Listado de salidas filtrado |
| `DeparturesMonthly` | XLS/HTML | Reporte mensual de salidas |
| `DeparturesMonthlyByStopExport` | XLS/HTML | Reporte mensual por paradero (RMP) |
| `DeparturesStatsMonthlyExport` | XLS/HTML | Estadistico de salidas |
| `DeparturesWorkbookExport` | XLSX | Workbook de salidas |
| `DeparturesMainExport` | XLSX | Sheet principal de salidas |
| `DeparturesSupportExport` | XLSX | Sheet de apoyos |
| `PaymentsExport` | XLS/HTML | Listado de pagos |
| `PaymentsMonthlyExport` | XLSX | Reporte mensual de pagos |
| `PaymentsDailyExport` | XLS/HTML | Reporte diario de pagos |
| `PaymentsStatsExport` | XLS/HTML | Estadistico de pagos |
| `IncomesExport` | XLS/HTML | Listado de ingresos |
| `ExpensesExport` | XLS/HTML | Listado de egresos |
| `DebtsPerDaysExport` | XLS/HTML | Deuda por dias |
| `DelayDetailsExport` | XLSX | Detalles de retrasos |
| `MonthlyDebtExport` | XLSX | Deuda mensual |
| `GeneralReportExport` | XLSX | Reporte general de caja |
| `RepEstDracoBaseExport` | XLSX | Reporte Draco Base |
| `RepEstPagContExport` | XLSX | Reporte Sal/Pag Contabilidad |
| `CajaEstadisticaExport` | XLSX | Estadistica de caja |
| `VehiclesReportExport` | XLS/HTML | Vehiculos |
| `UsersReportExport` | XLS/HTML | Usuarios |
| `ConceptsReportExport` | XLS/HTML | Conceptos |
| `HeadquartersReportExport` | XLS/HTML | Sucursales |

---

## 9. SERVICIOS

### 9.1 CostPerPlateGenerator (`app/Services/CostPerPlateGenerator.php`)

**Metodo principal:** `generateForMonth(Carbon $date): array`

**Logica de generacion:**

1. Determina mes destino (primer dia del mes de `$date`) y mes fuente (mes anterior).
2. Obtiene todos los vehiculos activos ordenados por `sort_order`.
3. Busca el monto del ultimo dia NO domingo del mes anterior por vehiculo.
4. Si no hay dato diario, usa fallback de 15.00.
5. Genera un registro mensual por vehiculo (`cost_per_plates`).
6. Genera un registro diario por vehiculo por cada dia del mes (`cost_per_plate_days`), todos con el mismo monto.
7. Dentro de una transaccion: elimina datos previos del mes destino y luego inserta en chunks de 1000.

**Retorno:** `['monthly' => N, 'daily' => N, 'skipped' => bool]`

---

## 10. COMANDOS ARTISAN

### 10.1 Comandos Operativos

#### `cost-per-plate:generate`

```bash
php artisan cost-per-plate:generate {--date=}
```

Genera costos por placa para el mes de la fecha indicada (default: hoy). Utiliza `CostPerPlateGenerator` service.

#### `debt-days:generate`

```bash
php artisan debt-days:generate {--year=} {--month=}
```

Genera deuda mensual por dias sin pago. **Logica legacy homologada:**

1. Crea respaldo JSON en `storage/debt-days-backup/`.
2. Carga vehiculos activos, pagos, salidas y costos diarios del mes.
3. Preserva exoneraciones/amortizaciones existentes.
4. Elimina registros previos del mes.
5. Por cada vehiculo y dia (excluyendo domingos):
   - **GN (General):** Sin pago = deuda.
   - **DT (Deuda Total):** Sin pago + con salida = deuda.
   - **EX5 (Exonerado 5):** Registra todos los dias, pero elimina los primeros 5 dias sin salida (gracia).
6. Calcula monto de deuda por dia usando `cost_per_plate_days`.
7. Restaura exoneraciones/amortizaciones previas.

#### `debt-days:rollback`

```bash
php artisan debt-days:rollback {--year=} {--month=}
```

Restaura `debt_days` desde el respaldo JSON generado por `debt-days:generate`.

#### `vehicles:deactivate-ceased`

```bash
php artisan vehicles:deactivate-ceased
```

Desactiva vehiculos cuya `termination_date` ya paso (marca como 'inactive').

### 10.2 Comandos de Setup

#### `taxivan:setup-production`

```bash
php artisan taxivan:setup-production {--skip-legacy} {--chunk=1000}
```

Ejecuta en secuencia:
- **Fase 0:** Seeders base (permisos, roles, sucursales, conceptos, usuarios).
- **Fase 1:** Migracion de maestros legacy (conductores, propietarios, vehiculos).
- **Fase 2:** Migracion de transacciones (salidas, pagos).
- **Fase 3:** Migracion financiera (egresos, ingresos, fix DRACO).
- **Fase 4:** Costos por placa (mensual y diario).
- **Fase 5:** Deudas (debt_days y debt_days_detail).

#### `users:assign-director`

```bash
php artisan users:assign-director {username}
```

Asigna rol Director y todos los permisos a un usuario existente.

#### `users:migrate-roles`

```bash
php artisan users:migrate-roles
```

Migra roles antiguos a nuevos: `superadmin -> director`, `admin -> administrador`, `controller -> controlador`. Elimina roles viejos.

### 10.3 Comandos de Migracion Legacy

| Comando | Descripcion |
|---|---|
| `taxivan:migrate-drivers` | Migra conductores desde tabla legacy Huachipa |
| `taxivan:migrate-owners` | Migra propietarios |
| `taxivan:migrate-vehicles` | Migra vehiculos |
| `taxivan:migrate-departures` | Migra salidas |
| `taxivan:migrate-payments` | Migra pagos |
| `taxivan:migrate-incomes` | Migra ingresos |
| `taxivan:migrate-expenses` | Migra egresos |
| `taxivan:migrate-debt-days` | Migra deuda por dias |
| `taxivan:migrate-debt-days-detail` | Migra detalle de deuda |
| `migrate:costpla` | Migra costo por placa mensual |
| `migrate:costpla-day` | Migra costo por placa diario |

### 10.4 Comandos de Mantenimiento

#### `taxivan:fix-vehicle-relations`

```bash
php artisan taxivan:fix-vehicle-relations {--source=huaca_taxi_vehiculos} {--dry-run}
```

Corrige `driver_id` y `owner_id` nulos en vehiculos consultando la tabla legacy.

#### `db:validate-data`

```bash
php artisan db:validate-data {--seconds=60}
```

Valida integridad de datos post-migracion (comando visual con barras de progreso).

#### `db:migrate-production`

```bash
php artisan db:migrate-production {--minutes=45}
```

Simulacion visual de migracion de base de datos con progreso animado (no ejecuta operaciones reales de datos).

---

## 11. SCHEDULER

Archivo: `routes/console.php`

| Comando | Frecuencia | Hora | Descripcion |
|---|---|---|---|
| `cost-per-plate:generate` | Dia 1 de cada mes | 02:00 AM | Genera costos mensuales automaticamente |
| `debt-days:generate` | Ultimo dia de cada mes | 23:00 PM | Genera deuda mensual automaticamente |
| `vehicles:deactivate-ceased` | Diariamente | 00:05 AM | Desactiva vehiculos con fecha de cese pasada |

**Configuracion comun:** Timezone `America/Lima`, `withoutOverlapping()`, `onOneServer()`.

---

## 12. SISTEMA DE ROLES Y PERMISOS

### 12.1 Roles

El sistema define 5 roles en orden jerarquico ascendente:

| Nivel | Rol | Descripcion |
|---|---|---|
| 1 | controlador | Registra salidas y pagos en su(s) sucursal(es) |
| 2 | supervisor | Supervision operativa |
| 3 | administrador | Gestion de configuraciones y reportes |
| 4 | gerente | Acceso amplio a reportes y configuraciones |
| 5 | director | Acceso total. Unico que puede gestionar roles/permisos |

### 12.2 Permisos (18 permisos)

Los permisos estan organizados por modulos:

#### Permisos Singles
| Permiso | Label | Descripcion |
|---|---|---|
| `dashboard` | Dashboard | Acceso al dashboard |
| `departures` | Salidas | Acceso a salidas |
| `payments` | Pagos | Acceso a pagos |

#### Modulo: Configuracion
| Permiso | Label |
|---|---|
| `configuracion.vehicles` | Vehiculos |
| `configuracion.owners` | Propietarios |
| `configuracion.drivers` | Conductores |
| `configuracion.cost-per-plate` | Costo por Placa |
| `configuracion.users` | Usuarios |
| `configuracion.concepts` | Conceptos |
| `configuracion.headquarters` | Sucursales |

#### Modulo: Deudas
| Permiso | Label |
|---|---|
| `debts.days` | Deuda x Dias |
| `debts.monthly` | Deuda Mensual |

#### Modulo: Caja
| Permiso | Label |
|---|---|
| `cash.incomes` | Ingreso |
| `cash.expenses` | Egreso |
| `cash.report-general` | Reporte General |
| `cash.report-draco` | Rep Est Draco Base |
| `cash.report-sal-pag-cont` | Rep Esp Sal Pag Cont |
| `cash.report-caja-ma` | Rep Est Caja M.A |

### 12.3 Gate::before

En `AppServiceProvider::boot()`:

```php
Gate::before(function ($user, $ability, $arguments) {
    // No hacer bypass para policies de Income/Expense
    if (!empty($arguments) && ($arguments[0] instanceof Income || $arguments[0] instanceof Expense)) {
        return null;
    }
    return $user->hasAnyRole('director','gerente') ? true : null;
});
```

**Efecto:** Director y Gerente tienen acceso a todo EXCEPTO las policies de Income y Expense, que se evaluan siempre.

### 12.4 Control de Acceso por Rol en Rutas

| Accion | Roles permitidos |
|---|---|
| Crear vehiculos/propietarios/conductores | director, gerente, administrador |
| Editar vehiculos/propietarios/conductores | director, gerente, administrador |
| Crear usuarios | director |
| Gestionar permisos | director, gerente, administrador |
| Reportes mensuales/estadisticos | director, gerente, administrador |
| Editar salidas/pagos | director, gerente, administrador |
| Crear sucursales | director, gerente, administrador |
| Editar sucursales | director |
| Auditoria | director |

### 12.5 Control de Acceso a Sucursales

Los usuarios no-admin solo pueden operar en las sucursales asignadas:
- **Sucursal primaria:** `users.headquarter_id`
- **Sucursales adicionales:** Tabla pivot `headquarter_user`
- Los admin (director/gerente/administrador) ven todas las sucursales.

---

## 13. POLICIES

### 13.1 IncomePolicy y ExpensePolicy

Ambas policies usan el trait `TransactionPolicyTrait`:

#### Metodo `create(User $user): bool`

Siempre retorna `true`. Cualquier usuario autenticado puede crear.

#### Metodo `update(User $user, Model $record): bool`

| Rol | Puede editar |
|---|---|
| Director | Siempre |
| Gerente, Administrador | Solo registros del dia actual |
| Supervisor, Controlador | Solo registros del dia actual Y que sean del mismo usuario (`record.user_id === user.id`) |
| Otros | Nunca |

#### Metodo `delete(User $user, Model $record): bool`

| Rol | Puede eliminar |
|---|---|
| Director | Siempre |
| Gerente, Administrador | Solo registros del dia actual |
| Otros | Nunca |

**Determinacion de "hoy":** Se usa `record.date` (si es Carbon) o `record.created_at` (timezone America/Lima).

---

## 14. TRAITS

### 14.1 Auditable (`app/Traits/Auditable.php`)

Trait que registra automaticamente eventos `created`, `updated` y `deleted` en la tabla `activity_logs`.

**Configuracion por modelo:**
- `$auditModule`: Nombre del modulo (string).
- `$auditExclude`: Campos a excluir del log (ej: `['password', 'remember_token']`).

**Eventos capturados:**
- `created`: Registra `new_data` completo.
- `updated`: Solo registra si hay campos dirty (excluyendo timestamps y campos excluidos). Guarda `old_data`, `new_data` y `changed_fields`.
- `deleted`: Registra `old_data` completo.

**Datos del log:** user_id, user_name, user_role, action, module, record_id, old/new_data, changed_fields, ip_address, user_agent.

**Manejo de errores:** Si el log falla, se registra en el logger pero no interrumpe la operacion.

### 14.2 TransactionPolicyTrait (`app/Traits/TransactionPolicyTrait.php`)

Logica compartida entre `IncomePolicy` y `ExpensePolicy`. Ver seccion 13.

### 14.3 CompactColumnWidths (`app/Traits/CompactColumnWidths.php`)

Trait para exports. Calcula anchos de columna optimos en PhpSpreadsheet, respetando celdas mergeadas.

---

## 15. ASSETS

### 15.1 Vite Configuration

**Archivo:** `vite.config.js`

```javascript
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',       // Tailwind CSS
                'resources/js/app.js',         // JS principal
                'public/assets/scss/style.scss' // SCSS personalizado
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: ['mixed-decls', 'color-functions',
                                     'global-builtin', 'import']
            },
        }
    }
});
```

### 15.2 Entry Points

| Archivo | Descripcion |
|---|---|
| `resources/css/app.css` | Tailwind CSS con directivas @tailwind |
| `resources/js/app.js` | JavaScript principal de la aplicacion |
| `public/assets/scss/style.scss` | Estilos SCSS personalizados (warnings SASS suprimidos) |

### 15.3 Comandos de Build

```bash
npm run dev      # Servidor Vite con hot reload
npm run build    # Build de produccion
```

---

## 16. CONFIGURACIONES CLAVE

### 16.1 Aplicacion

| Configuracion | Valor |
|---|---|
| Locale | `es` |
| Timezone | `America/Lima` |
| Framework | Laravel 11 |
| Guard | `web` |

### 16.2 AppServiceProvider

**Archivo:** `app/Providers/AppServiceProvider.php`

**Registros en `boot()`:**

1. **Policies:** Registra `IncomePolicy` y `ExpensePolicy` via `Gate::policy()`.
2. **Gate::before:** Bypass para director/gerente excepto en policies de Income/Expense.
3. **View Composer** (`layout.header`): Cachea alertas de vehiculos con documentos por vencer (SOAT, Revision Tecnica, Certificado) por 60 segundos. Muestra hasta 10 alertas en el dropdown del header, ordenadas por urgencia.

### 16.3 Autenticacion

- Login por **username** (no email).
- Rate limiting: 5 intentos por username+IP.
- Redireccion post-login: `/departures`.
- Guard: `web`.

---

## 17. DEPLOY

### 17.1 Script `deploy.sh`

**Archivo:** `deploy.sh`

```bash
#!/bin/bash
set -e

cd "$(dirname "$0")"

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan db:seed --class=RoleSetupSeeder --force
php artisan db:seed --class=PermissionCatalogSeeder --force
php artisan users:migrate-roles
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```

**Pasos:**

1. Pull de cambios desde `main`.
2. Instala dependencias PHP (sin dev).
3. Ejecuta migraciones pendientes.
4. Crea roles nuevos si no existen.
5. Crea/actualiza catalogo de permisos.
6. Migra roles de usuarios antiguos a nuevos.
7. Cachea configuracion, rutas y vistas.
8. Resetea cache de permisos de Spatie.

### 17.2 Comandos de Setup Inicial

```bash
# Migraciones
php artisan migrate

# Setup completo (seeders + migracion legacy)
php artisan taxivan:setup-production

# Solo seeders (sin legacy)
php artisan taxivan:setup-production --skip-legacy

# Asignar director
php artisan users:assign-director {username}
```

### 17.3 Desarrollo Local

```bash
# Stack completo
composer run dev

# Individuales
php artisan serve         # PHP server :8000
npm run dev               # Vite dev server
```

---

## 18. SEEDERS

### 18.1 Lista de Seeders

| Seeder | Descripcion |
|---|---|
| `DatabaseSeeder` | Seeder principal (orquestador) |
| `PermissionCatalogSeeder` | Crea 18 permisos organizados por modulos. Limpia permisos previos (trunca tablas pivot) |
| `RoleSetupSeeder` | Crea 5 roles: director, gerente, administrador, supervisor, controlador |
| `HeadquartersSeeder` | Crea sucursales iniciales |
| `ConceptsSeeder` | Crea conceptos de ingresos/egresos |
| `UsersSeeder` | Crea usuarios iniciales con roles |
| `RolesAndPermissionsSeeder` | Seeder legacy de roles/permisos |
| `GrantAdminToUserOneSeeder` | Otorga admin al usuario #1 |

### 18.2 PermissionCatalogSeeder (Detalle)

Proceso:
1. Limpia cache de Spatie.
2. Trunca tablas: `role_has_permissions`, `model_has_permissions`, `permissions`.
3. Crea 18 permisos con metadata: `name`, `label`, `module`, `module_label`, `description`.
4. Limpia cache nuevamente.

**Importante:** Este seeder ELIMINA todos los permisos y asignaciones previas. Debe re-asignarse permisos a usuarios despues de ejecutarlo.

---

## APENDICE A: CONDICIONES DE VEHICULOS

| Condicion | Significado | Comportamiento en Deuda |
|---|---|---|
| GN | General | Dia sin pago = deuda (todos los dias no domingo) |
| DT | Deuda Total | Solo genera deuda si tuvo salida ese dia sin pago |
| EX | Exonerado | Se incluye en grilla con 0 dias y 0 total |
| EX5 | Exonerado 5 dias | Como GN pero con 5 dias de gracia sin salida |

## APENDICE B: LOGICA DE BADGES DE VEHICULOS

Los badges muestran alertas de vencimiento en la interfaz:

| Abreviatura | Documento | Color |
|---|---|---|
| SD | SOAT | rojo (vencido/hoy/<=5d), amarillo (6-10d) |
| RT | Revision Tecnica | rojo (vencido/hoy/<=5d), amarillo (6-10d) |
| CD | Certificado | rojo (vencido/hoy/<=5d), amarillo (6-10d) |

Solo aparecen si faltan 10 dias o menos para el vencimiento, o si ya esta vencido.

## APENDICE C: FLUJO DE RESOLUCION DE VEHICULO POR PLACA

Usado en AddDeparture, EditDeparture, AddPayment, EditPayment:

1. Normaliza placa a mayusculas, elimina espacios internos.
2. Busca en `vehicles` por `UPPER(TRIM(plate))`.
3. Si existe, es activo y no esta cesado: `vehicle_id = id`, `is_support = 0`.
4. Si existe pero esta inactivo/cesado: `vehicle_id = null`, `is_support = 1`, `legacy_plate = placa`.
5. Si no existe: `vehicle_id = null`, `is_support = 1`, `legacy_plate = placa`.

---

*Documento generado automaticamente desde el analisis del codebase del proyecto TaxiVan.*
*Ultima actualizacion: 2026-04-01*
