# Portal privado para entrenadores

Portal privado del club donde los entrenadores pueden publicar, organizar y compartir
vídeos, sesiones de entrenamiento, documentos, tareas, jugadas y enlaces útiles.

## Estructura

```
backend/     API REST (PHP / Laravel)
frontend/    Página de pruebas (HTML + CSS + JS)
```

Los archivos subidos se guardan en `backend/storage/app/private`, fuera de la base de datos
y sin acceso directo desde internet. No se versionan.

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


### Publicaciones y adjuntos

Con una cuenta aprobada, en la tarjeta «Publicaciones»:

1. «Nueva publicación»: título, tipo, quién puede verla, equipo, temporada y contenido.
2. En el mismo formulario se pueden adjuntar imágenes (JPG, PNG o WebP) y PDF, hasta 10 MB
   cada uno, y un enlace de YouTube. Máximo 10 adjuntos por publicación.
3. La lista se puede filtrar por tipo y buscar por título.
4. El autor o un administrador pueden editar, eliminar y quitar adjuntos. Los demás solo
   pueden verlos.

Valores válidos: `tipo` = video, entrenamiento, tarea, jugada o recurso;
`visibilidad` = entrenadores (por defecto) o todos.
Filtros de `GET /posts`: `tipo`, `team_id`, `temporada`, `user_id` y `q` (busca en el título).

También se puede probar con Postman o curl usando la tabla de la API de abajo y la
cabecera `Authorization: Bearer TOKEN` (el token lo devuelve `POST /api/login`).

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
| POST | `/media` | Adjuntar a una publicación un archivo (`archivo`) o un enlace de YouTube (`url`), junto con `post_id` |
| GET | `/media/{id}/download` | Descargar un archivo, si se puede ver su publicación |
| DELETE | `/media/{id}` | Quitar un adjunto (su autor o un administrador) |
| GET | `/users` | Listar usuarios (administrador) |
| PUT | `/users/{id}/approve` | Aprobar una cuenta (administrador) |
| PUT | `/users/{id}/deactivate` | Desactivar una cuenta (administrador) |

## Seguridad

- Contraseñas con hash seguro, nunca en texto plano.
- Permisos comprobados siempre en el backend.
- Las cuentas nuevas no pueden entrar hasta que un administrador las aprueba.
- Los archivos se validan por tamaño y por su contenido real, se guardan con un nombre
  aleatorio y solo se descargan a través de la API, comprobando los permisos.
- Secretos en `.env`, nunca en el repositorio.
