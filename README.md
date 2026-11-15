# GreenCycle

Aplicación web gamificada para administrar un vivero digital. Cada persona usuaria
autenticada puede plantar árboles, cuidarlos, hacerlos crecer y —a partir del
Sprint 3— cosecharlos, comprar ítems y administrar su inventario.

Proyecto del curso **TM4100**.

## Estado del proyecto

- Sprint 1: autenticación con Laravel Sanctum, CRUD básico de árboles y frontend.
- Sprint 2: cuidado de árboles con cooldown, salud, progreso, niveles y estado `MATURE`.
- Sprint 3: economía, cosecha, tienda, inventario, mejoras y despliegue en Render.

El código del Sprint 2 fue migrado a este repositorio desde la rama `Sprint-2`
del repositorio histórico `Ethankabalzt/GreenCycle`.

## Tecnologías

- PHP 8.5 y Laravel 13.
- Laravel Sanctum para tokens de API.
- Blade, HTML, CSS y JavaScript con Fetch API.
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
```

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

El seeder crea la cuenta `demo@greencycle.test` y un árbol inicial. La contraseña
se entrega por el medio privado indicado por la persona docente y no se publica
en este repositorio.

## Modelo de datos

| Tabla | Propósito |
|---|---|
| `users` | Cuentas y autenticación. |
| `seed_types` | Catálogo de tipos de semilla y recompensa de cosecha. |
| `trees` | Árboles: nivel, salud, progreso, estado, cooldown y cosecha. |
| `cares` | Historial de cuidados aplicados. |
| `shop_items` | Catálogo de la tienda (Sprint 3). |
| `inventory_items` | Ítems por usuario (Sprint 3). |
| `purchases` | Compras realizadas (Sprint 3). |
| `active_effects` | Efectos activos sobre árboles (Sprint 3). |

## API

Todas las rutas usan el prefijo `/api` y las rutas protegidas requieren
`Authorization: Bearer <token>`.

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/auth/register` | Registrar usuario. |
| POST | `/api/auth/login` | Iniciar sesión y obtener token. |
| POST | `/api/auth/logout` | Cerrar la sesión actual. |
| GET | `/api/user` | Usuario autenticado. |
| GET | `/api/trees` | Árboles del usuario. |
| POST | `/api/trees` | Plantar un árbol. |
| GET | `/api/trees/{tree}` | Detalle de un árbol propio. |
| POST | `/api/trees/{tree}/care` | Aplicar un cuidado. |

Respuesta de cuidado:

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

Códigos relevantes:

- `401`: no autenticado.
- `403`: el recurso pertenece a otra persona.
- `409`: cooldown activo o estado terminal.
- `422`: datos inválidos.

## Reglas de cuidado

- Un árbol en estado `DEAD` o `MATURE` no recibe más cuidados.
- Si `next_care_at` está en el futuro, la petición se rechaza sin modificar datos.
- Un cuidado válido suma progreso y recupera salud sin superar 100.
- Al completar los cuidados del nivel, el nivel sube y el progreso vuelve a 0.
- Al llegar al nivel 10, el árbol pasa a `MATURE`.

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

El script `scripts/render-start.sh` aplica las migraciones y arranca FrankenPHP.

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

Documentar aquí las herramientas de IA utilizadas y los recursos consultados por
el equipo antes de la entrega final.

## Equipo

- Jaret
- Ethan
