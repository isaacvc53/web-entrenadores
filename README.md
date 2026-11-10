# Portal privado para entrenadores

Portal privado de la UD Estrellas Chana Barrio donde los entrenadores pueden publicar,
organizar y compartir vídeos, sesiones de entrenamiento, documentos, tareas, jugadas y
enlaces útiles.

Está en <https://www.udechanabarrio.com/entrenadores/>.

## Estructura

```
backend/     API REST (PHP 8.3 / Laravel 13 / Sanctum)
frontend/    La página (HTML + CSS + JavaScript, sin compilar)
```

Los archivos subidos se guardan en `backend/storage/app/private`, fuera de la base de datos
y sin acceso directo desde internet. No se versionan.

## Instalación

Requisitos: PHP 8.3 con la extensión GD, Composer y MySQL. En Windows, Laragon incluye los tres.

1. Crear una base de datos vacía llamada `portal_entrenadores` (utf8mb4).
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

Página, desde la raíz del repositorio:

```bash
php -S 127.0.0.1:8020 -t frontend
```

- <http://127.0.0.1:8010/api/health> debe responder `{"status":"ok","database":"ok"}`.
- En <http://127.0.0.1:8020> se puede crear una cuenta, entrar con el administrador,
  aprobarla y entrar con ella.

La página sabe sola dónde está la API: en local usa el puerto 8010 y en el servidor la
carpeta `api` que tiene al lado.

## Cómo funciona

### Cuentas y roles

- Cualquiera puede crear una cuenta, pero no puede entrar hasta que un administrador la aprueba.
- **Entrenador**: publica, comenta y ve todo lo publicado.
- **Administrador**: además gestiona usuarios, categorías, tipos, equipos y etiquetas.
- La sesión caduca a los 30 días (o al cerrar la pestaña). Se cambia con `SANCTUM_EXPIRATION`
  en el `.env`, en minutos.
- No se envían correos. Si alguien olvida la contraseña, un administrador le pone una
  temporal desde el panel de usuarios y luego cada uno la cambia en «Mi perfil».

### Publicaciones

- Título, tipo, equipo (opcional), temporada (si se elige equipo, se rellena con la suya),
  contenido y etiquetas.
- Se pueden adjuntar imágenes (JPG, PNG o WebP) y PDF, hasta 10 MB cada uno, y enlaces de
  YouTube. Máximo 10 adjuntos por publicación.
- Las fotos se ven con una miniatura de 640 px que el servidor crea la primera vez que se pide.
  La original solo se descarga al ampliarla.
- Filtros: tipo, equipo, temporada (la de la publicación o la de su equipo), etiqueta,
  «Solo mis publicaciones» y búsqueda por título. Salen de 20 en 20, con «Cargar más».
- Solo el autor o un administrador pueden editar, eliminar y quitar adjuntos.

### Etiquetas

- La **lista fija** la pone el administrador y sale siempre para elegir al publicar.
- Los entrenadores pueden escribir etiquetas nuevas al publicar. Se borran solas cuando
  ya no las tiene ninguna publicación, salvo que el administrador las pase a la lista fija.
- Al comparar no se distinguen mayúsculas ni tildes: escribir «tactica» marca «Táctica».
- Máximo 10 etiquetas por publicación y 30 caracteres por etiqueta.

### Recursos

Todos los archivos y vídeos de todas las publicaciones juntos, con los mismos filtros que
las publicaciones y además por clase de archivo (imagen, PDF o vídeo). Desde cada uno se
abre su publicación con los comentarios.

### Comentarios

Cada publicación tiene comentarios. El autor de un comentario o un administrador pueden
borrarlo, y el administrador puede ocultarlo (moderarlo) sin borrarlo.

### Mi perfil

Foto de perfil (se recorta sola a un cuadrado de 256 px; si no hay, salen las iniciales) y
cambio de contraseña. Al cambiarla se cierran las sesiones de los demás dispositivos.

## API

Todas las rutas empiezan por `/api`. Salvo las tres primeras, exigen la cabecera
`Authorization: Bearer TOKEN` (lo devuelve `POST /api/login`) y una cuenta aprobada.
Se puede probar con Postman o curl.

### Cuenta

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/health` | Estado de la API y de la base de datos |
| POST | `/register` | Crear una cuenta, pendiente de aprobación |
| POST | `/login` | Iniciar sesión y obtener un token |
| POST | `/logout` | Cerrar sesión |
| GET | `/user` | Datos del usuario conectado |
| PUT | `/user/password` | Cambiar la propia contraseña (`password_actual`, `password`, `password_confirmation`) |
| POST | `/user/photo` | Subir la foto de perfil (`foto`) |
| DELETE | `/user/photo` | Quitar la foto de perfil |
| GET | `/users/{id}/photo` | Ver la foto de un usuario |

### Publicaciones, adjuntos y comentarios

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/categories` | Categorías, tipos, equipos, temporadas y etiquetas |
| GET | `/posts` | Listar publicaciones. Filtros: `type_id`, `team_id`, `temporada`, `tag_id`, `user_id`, `q`, `page` |
| POST | `/posts` | Crear una publicación (`titulo`, `type_id`, `team_id`, `temporada`, `contenido`, `etiquetas`) |
| GET | `/posts/{id}` | Ver una publicación |
| PUT | `/posts/{id}` | Editarla (su autor o un administrador) |
| DELETE | `/posts/{id}` | Eliminarla, con sus archivos (su autor o un administrador) |
| GET | `/media` | Listar adjuntos (Recursos). Los mismos filtros que `/posts` y `tipo` (imagen, pdf o youtube) |
| POST | `/media` | Adjuntar a una publicación (`post_id`) un archivo (`archivo`) o un enlace de YouTube (`url`) |
| GET | `/media/{id}/download` | Descargar un archivo |
| GET | `/media/{id}/thumbnail` | Miniatura de una imagen |
| DELETE | `/media/{id}` | Quitar un adjunto (su autor o un administrador) |
| GET | `/posts/{id}/comments` | Comentarios de una publicación |
| POST | `/posts/{id}/comments` | Comentar (`contenido`) |
| DELETE | `/comments/{id}` | Borrar un comentario (su autor o un administrador) |
| PUT | `/comments/{id}/moderate` | Ocultarlo o volver a mostrarlo (`moderado`, administrador) |

`etiquetas` es una lista de nombres. Las que no existen se crean; si no se envía al editar,
la publicación conserva las que tenía.

### Administración (solo administradores)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/users` | Listar usuarios (`activo=0` o `activo=1` para filtrar) |
| PUT | `/users/{id}/approve` | Aprobar una cuenta |
| PUT | `/users/{id}/deactivate` | Desactivarla y cerrar sus sesiones |
| PUT | `/users/{id}/role` | Cambiar el rol (`rol`: admin o entrenador; el propio no) |
| PUT | `/users/{id}/password` | Ponerle una contraseña temporal; la devuelve una sola vez |
| POST, PUT, DELETE | `/categories`, `/categories/{id}` | Crear, renombrar y borrar categorías (`nombre`) |
| PUT | `/categories/order` | Ordenar las categorías (`ids` en orden) |
| POST, PUT, DELETE | `/post-types`, `/post-types/{id}` | Lo mismo con los tipos de publicación |
| PUT | `/post-types/order` | Ordenar los tipos |
| POST, PUT, DELETE | `/teams`, `/teams/{id}` | Equipos (`nombre`, `category_id`, `temporada`) |
| POST | `/teams/copy-season` | Copiar los equipos de una temporada a otra (`desde`, `hasta`) |
| POST, PUT, DELETE | `/tags`, `/tags/{id}` | Etiquetas (`nombre`, `fija`) |

Las categorías y los tipos que están en uso no se pueden borrar. Las etiquetas sí: las
publicaciones se quedan sin ellas.

## Despliegue

El servidor es un alojamiento compartido de OVH sin SSH. Se sube por SFTP:

- El backend completo, con `vendor/` y su `.env` de producción, va fuera de la carpeta
  pública.
- En `www/entrenadores` van la página y un `index.php` con un `.htaccess` que pasa las
  rutas `/api` a Laravel y obliga a usar https.

Como no se puede ejecutar `php artisan migrate`, cada cambio de la base de datos se prepara
como un `.sql` que se importa en phpMyAdmin **antes** de subir el código nuevo.

La carpeta de despliegue y las contraseñas no están en el repositorio.

## Seguridad

- Contraseñas con hash seguro, nunca en texto plano.
- Permisos comprobados siempre en el backend.
- Las cuentas nuevas no pueden entrar hasta que un administrador las aprueba.
- Límite de intentos en el login, el registro, el cambio de contraseña y los comentarios.
- Las sesiones caducan a los 30 días, y las caducadas se borran al volver a entrar.
- Los archivos se validan por tamaño y por su contenido real, se guardan con un nombre
  aleatorio y solo se descargan a través de la API, comprobando los permisos.
- Secretos en `.env`, nunca en el repositorio.
