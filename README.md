# Portal privado para entrenadores

Portal privado del club donde los entrenadores pueden publicar, organizar y compartir
vídeos, sesiones de entrenamiento, documentos, tareas, jugadas y enlaces útiles.

## Estructura

```
backend/     API REST (PHP / Laravel)
frontend/    Página de pruebas (HTML + CSS + JS)
storage/     Archivos subidos (fuera de la base de datos, no se versionan)
```

## Instalación

Requisitos: PHP 8.3, Composer y MySQL. En Windows, Laragon incluye los tres.

1. Crear una base de datos vacía llamada `portal_entrenadores`.
2. Desde la carpeta `backend`:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

3. Poner en `backend/.env` los datos de la base de datos (`DB_CONNECTION=mysql`,
   `DB_DATABASE=portal_entrenadores`, usuario y contraseña).
4. Crear las tablas y el primer administrador:

   ```bash
   php artisan migrate
   php artisan app:crear-admin
   ```

## Arranque

Backend, desde la carpeta `backend`:

```bash
php artisan serve --port=8010
```

Página de pruebas, desde la raíz del repositorio:

```bash
php -S 127.0.0.1:8020 -t frontend
```

## Cómo probarlo

- <http://127.0.0.1:8010/api/health> debe responder `{"status":"ok","database":"ok"}`.
- En <http://127.0.0.1:8020> se puede crear una cuenta, entrar con el administrador,
  aprobarla y entrar con ella.

## API

Todas las rutas empiezan por `/api`. Salvo las tres primeras, exigen la cabecera
`Authorization: Bearer TOKEN` y una cuenta aprobada.

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/health` | Estado de la API y de la base de datos |
| POST | `/register` | Crear una cuenta, pendiente de aprobación |
| POST | `/login` | Iniciar sesión y obtener un token |
| POST | `/logout` | Cerrar sesión |
| GET | `/user` | Datos del usuario conectado |
| GET | `/categories` | Tipos, visibilidades y equipos |
| GET | `/posts` | Listar publicaciones, con filtros |
| POST | `/posts` | Crear una publicación |
| GET | `/posts/{id}` | Ver una publicación |
| PUT | `/posts/{id}` | Editarla (su autor o un administrador) |
| DELETE | `/posts/{id}` | Eliminarla (su autor o un administrador) |
| GET | `/users` | Listar usuarios (administrador) |
| PUT | `/users/{id}/approve` | Aprobar una cuenta (administrador) |
| PUT | `/users/{id}/deactivate` | Desactivar una cuenta (administrador) |

## Seguridad

- Contraseñas con hash seguro, nunca en texto plano.
- Permisos comprobados siempre en el backend.
- Las cuentas nuevas no pueden entrar hasta que un administrador las aprueba.
- Secretos en `.env`, nunca en el repositorio.
