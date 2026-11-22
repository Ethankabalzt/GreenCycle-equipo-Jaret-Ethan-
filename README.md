# GreenCycle

Aplicación web gamificada para administrar un vivero digital. Cada persona usuaria
autenticada puede plantar árboles, cuidarlos, hacerlos crecer, cosecharlos,
comprar ítems y administrar su inventario.

Proyecto del curso **TM4100**.

## Estado del proyecto

- **Sprint 1:** autenticación con Laravel Sanctum, CRUD básico de árboles y frontend.
- **Sprint 2:** cuidado de árboles con cooldown, salud, progreso, niveles y estado `MATURE`.
- **Sprint 3:** economía (monedas), cosecha única, tienda, inventario, efectos/mejoras, deterioro programado y frontend integrado.

El código del Sprint 2 fue migrado a este repositorio desde la rama `Sprint-2`
del repositorio histórico `Ethankabalzt/GreenCycle`.

## Tecnologías

- PHP 8.5 y Laravel 13.
- Laravel Sanctum para tokens de API.
- HTML, CSS y JavaScript con Fetch API.
- Vite.
- PostgreSQL en Neon.
- PHPUnit y Laravel Pint.
- GitHub Actions.
- Render con Docker y FrankenPHP.

## Ambientes

| Ambiente | Aplicación | Base de datos |
|---|---|---|
| Desarrollo | Laravel Herd | Neon `development` |
| Pruebas / CI | PHPUnit | SQLite en memoria |
| Producción | Render | Neon `production` |

Las credenciales de `production` nunca se usan en local y las de `development`
nunca se configuran en Render.

## Requisitos locales

- Windows 10 o superior.
- Laravel Herd.
- PHP 8.5.
- Composer 2.
- Node.js 26 y npm.
- Git.
- Cuenta de Neon.

## Instalación local

```powershell
git clone https://github.com/Ethankabalzt/GreenCycle-equipo-Jaret-Ethan-.git
Set-Location GreenCycle-equipo-Jaret-Ethan-
herd isolate 8.5
nvm use 26
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Configurar en `.env` la cadena **directa** de la rama `development` de Neon:

```dotenv
DB_CONNECTION=pgsql
DB_URL="postgresql://...development..."
DB_SSLMODE=require
DEMO_PASSWORD="una-clave-demo-local"
```

> `DEMO_PASSWORD` la usa el seeder para crear la cuenta demo local/desarrollo.
> No se publica ni se versiona.

Comprobar la conexión antes de migrar:

```powershell
php artisan optimize:clear
php artisan db:show
```

Reconstruir la base de datos de desarrollo y cargar datos demo:

```powershell
php artisan migrate:fresh --seed
```

> `migrate:fresh` está prohibido contra la base de datos de producción.

Iniciar el frontend en modo desarrollo:

```powershell
npm run dev
```

La aplicación queda disponible en `http://greencycle-sprint3.test`.

## Cuenta demo

Si `DEMO_PASSWORD` está definida al ejecutar el seeder, se crea la cuenta
`demo@greencycle.test` con 500 monedas, un árbol activo y un árbol maduro listo
para cosechar. La contraseña se entrega por un medio privado y no se publica en
este repositorio.

## Modelo de datos

| Tabla | Propósito |
|---|---|
| `users` | Cuentas, autenticación y saldo de monedas (`coins`). |
| `seed_types` | Catálogo de semillas: cuidados por nivel y recompensa de cosecha. |
| `trees` | Árboles: nivel, salud, progreso, estado, cooldown, deterioro y cosecha. |
| `cares` | Historial de cuidados aplicados. |
| `shop_items` | Catálogo de la tienda: tipo, costo, valor/duración de efecto. |
| `inventory_items` | Ítems por usuario; único por `(user_id, shop_item_id)`. |
| `purchases` | Historial de compras. |
| `active_effects` | Efectos activos aplicados a árboles. |

Estados de árbol: `ACTIVE`, `MATURE`, `HARVESTED`, `DEAD`.

## API

Todas las rutas usan el prefijo `/api`. Las rutas protegidas requieren
`Authorization: Bearer <token>`.

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| POST | `/api/auth/register` | Registrar usuario. | No |
| POST | `/api/auth/login` | Iniciar sesión y obtener token. | No |
| POST | `/api/auth/logout` | Cerrar la sesión actual. | Sí |
| GET | `/api/user` | Usuario autenticado (incluye `coins`). | Sí |
| GET | `/api/seed-types` | Catálogo de tipos de semilla. | Sí |
| GET | `/api/trees` | Árboles del usuario. | Sí |
| POST | `/api/trees` | Plantar un árbol. | Sí |
| GET | `/api/trees/{tree}` | Detalle de un árbol propio. | Sí |
| POST | `/api/trees/{tree}/care` | Aplicar un cuidado. | Sí |
| POST | `/api/trees/{tree}/harvest` | Cosechar un árbol maduro. | Sí |
| GET | `/api/shop/items` | Catálogo de la tienda. | Sí |
| POST | `/api/shop/purchases` | Comprar ítems (acepta `idempotency_key` opcional). | Sí |
| GET | `/api/inventory` | Inventario del usuario. | Sí |
| POST | `/api/inventory/{inventoryItem}/use` | Usar un ítem sobre un árbol propio. | Sí |

### Ejemplo: cuidado

```json
{
  "message": "Cuidado aplicado con éxito",
  "tree": {
    "id": 1,
    "level": 1,
    "health": 100,
    "progress": 0,
    "status": "ACTIVE",
    "next_care_at": "2026-11-15T18:00:00Z"
  }
}
```

### Ejemplo: cosecha

```json
{
  "message": "Cosecha completada con éxito",
  "reward": 100,
  "coins": 600,
  "tree": {
    "id": 2,
    "status": "HARVESTED"
  }
}
```

Códigos relevantes:

- `401`: no autenticado.
- `403`: el recurso pertenece a otra persona.
- `409`: regla de negocio inválida (cooldown, estado, saldo, cosecha repetida, etc.).
- `422`: datos inválidos.

## Reglas de negocio

### Cuidado y crecimiento

- Un árbol en estado `DEAD`, `MATURE` o `HARVESTED` no recibe cuidados.
- Si `next_care_at` está en el futuro, el cuidado se rechaza sin modificar datos.
- Un cuidado válido suma progreso y recupera salud sin superar 100.
- Al completar los cuidados del nivel, el nivel sube y el progreso vuelve a 0.
- Al llegar al nivel 10, el árbol pasa a `MATURE`.

### Economía y cosecha

- Cada usuario tiene saldo `coins`.
- Solo un árbol `MATURE` no cosechado puede cosecharse.
- La cosecha es única: acredita `seed_types.harvest_coins`, marca el árbol como `HARVESTED` y guarda `harvested_at`.
- La compra descuenta monedas, valida saldo, registra la compra y acumula el ítem en inventario. Un reintento con la misma `idempotency_key` no duplica la compra.

### Ítems y efectos

- `ACCELERATOR`: reduce/elimina el cooldown pendiente del cuidado.
- `FERTILIZER`: suma progreso y puede provocar subidas de nivel o madurez.
- `PROTECTOR`: crea un efecto activo con duración; evita deterioro mientras esté vigente y no se permite duplicado simultáneo.
- Los ítems solo se aplican a árboles propios en estado `ACTIVE`.

### Deterioro programado

- `trees:apply-decay`: aplica deterioro a árboles `ACTIVE` sin protector activo, por intervalos transcurridos. Si la salud llega a 0, el árbol pasa a `DEAD`.
- `effects:release-expired`: desactiva efectos vencidos.

El scheduler de Laravel ejecuta ambos comandos cada hora (ver `bootstrap/app.php`).

## Comprobaciones

Antes de un commit o pull request:

```powershell
php artisan test
.\vendor\bin\pint --test
npm run build
```

Para corregir el formato:

```powershell
.\vendor\bin\pint
```

## Despliegue en Render

`render.yaml` define un servicio Docker con health check en `/up` y despliegue
automático cuando los checks de CI terminan correctamente.

Secretos requeridos en Render:

| Variable | Valor |
|---|---|
| `APP_KEY` | Clave exclusiva de producción (`php artisan key:generate --show`). |
| `DB_URL` | Conexión directa de Neon `production`. |

Configuración obligatoria:

- `APP_DEBUG=false`
- `APP_ENV=production`
- `DB_CONNECTION=pgsql`
- `DB_SSLMODE=require`
- Rama `main`
- Runtime Docker

El script `scripts/render-start.sh` ejecuta `php artisan migrate --force`,
optimiza la aplicación y arranca FrankenPHP. **No ejecutar `migrate:fresh` en
producción.**

Para la cuenta demo en producción, definir `DEMO_PASSWORD` solo si se desea
sembrar/gestionar la cuenta por un proceso controlado; no publicar el valor.

## Seguridad

No deben publicarse en el repositorio:

- El archivo `.env`.
- `APP_KEY` reales.
- Cadenas de conexión de Neon.
- Tokens de GitHub o Render.
- Contraseñas de cuentas demo.
- Bases de datos locales como `database.sqlite`.

Antes de cada commit:

```powershell
git status --short
git check-ignore .env
```

## Flujo de trabajo

1. Actualizar `main`.
2. Crear una rama `feature/`, `fix/`, `chore/` o `docs/`.
3. Desarrollar y ejecutar las comprobaciones.
4. Crear el pull request.
5. Esperar los checks de GitHub Actions.
6. Hacer Squash and merge.
7. Actualizar el repositorio local.

## Uso de IA y recursos externos

- **OpenCode / agente de IA:** apoyo para implementar, depurar, probar y documentar
  funcionalidades del Sprint 3. Todo el código y la documentación generados fueron
  revisados y validados por el equipo antes de integrarse.
- **Recursos externos:** documentación oficial de Laravel, Laravel Sanctum,
  PHPUnit, PostgreSQL/Neon, Render y GitHub Actions.

El equipo debe revisar y complementar esta sección si se utilizaron otras
herramientas o fuentes durante la entrega final.

## Equipo

- Jaret
- Ethan
