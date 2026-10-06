# Portal privado para entrenadores

Portal privado del club donde los entrenadores pueden publicar, organizar y compartir
vídeos, sesiones de entrenamiento, documentos, tareas, jugadas y enlaces útiles.


## Estructura

```
backend/     API REST (PHP / Laravel)
frontend/    Interfaz (Svelte/SvelteKit o HTML + CSS + JS)
storage/     Archivos subidos (fuera de la base de datos, no se versionan)
```


## Seguridad

- Contraseñas con hash seguro, nunca en texto plano.
- Permisos comprobados siempre en el backend.
- Archivos privados no accesibles solo por conocer su URL.
- Secretos en `.env`, nunca en el repositorio.
