// Dirección de la API del backend en local.
const API = 'http://127.0.0.1:8010/api';

const CLAVE_TOKEN = 'portal_token';

const $ = (id) => document.getElementById(id);

// Tamaño máximo de un adjunto; el backend aplica el mismo límite.
const MAX_BYTES_ADJUNTO = 10 * 1024 * 1024;

let usuario = null;
let postEditando = null;
let urlsDeAdjuntos = [];

// --- Llamadas a la API ---

// Hace la petición con el token y lanza ErrorApi si la respuesta no es correcta.
// Acepta datos en JSON o un FormData (para subir archivos).
async function peticion(ruta, { metodo = 'GET', datos } = {}) {
    const cabeceras = { Accept: 'application/json' };
    const token = sessionStorage.getItem(CLAVE_TOKEN);
    const esFormulario = datos instanceof FormData;

    if (token) {
        cabeceras.Authorization = `Bearer ${token}`;
    }
    if (datos && !esFormulario) {
        cabeceras['Content-Type'] = 'application/json';
    }

    let respuesta;
    try {
        respuesta = await fetch(API + ruta, {
            method: metodo,
            headers: cabeceras,
            body: datos ? (esFormulario ? datos : JSON.stringify(datos)) : undefined,
        });
    } catch {
        throw new ErrorApi(0, 'No se pudo conectar con la API. ¿Está arrancado el backend?');
    }

    if (!respuesta.ok) {
        const cuerpo = await respuesta.json().catch(() => ({}));
        throw new ErrorApi(respuesta.status, mensajeDeError(respuesta.status, cuerpo));
    }

    return respuesta;
}

async function api(ruta, opciones) {
    const respuesta = await peticion(ruta, opciones);

    return respuesta.json().catch(() => ({}));
}

async function descargarAdjunto(media) {
    const respuesta = await peticion(`/media/${media.id}/download`);

    return respuesta.blob();
}

class ErrorApi extends Error {
    constructor(estado, mensaje) {
        super(mensaje);
        this.estado = estado;
    }
}

function mensajeDeError(estado, cuerpo) {
    if (estado === 429) {
        return 'Demasiados intentos. Espera un minuto y vuelve a probar.';
    }
    if (cuerpo.errors) {
        return Object.values(cuerpo.errors).flat().join('\n');
    }
    return cuerpo.message || `Error ${estado}`;
}

// --- Avisos ---

function avisar(texto, tipo = 'ok') {
    const aviso = $('aviso');
    aviso.textContent = texto;
    aviso.className = `aviso ${tipo}`;
    aviso.hidden = false;
}

function limpiarAviso() {
    $('aviso').hidden = true;
}

// --- Vistas ---

function mostrarAcceso() {
    usuario = null;
    liberarAdjuntos();
    $('lista-posts').replaceChildren();
    $('vista-sesion').hidden = true;
    $('vista-acceso').hidden = false;
}

function mostrarSesion() {
    $('vista-acceso').hidden = true;
    $('vista-sesion').hidden = false;

    $('dato-nombre').textContent = usuario.nombre;
    $('dato-email').textContent = usuario.email;
    $('dato-rol').textContent = usuario.rol;

    const esAdmin = usuario.rol === 'admin';
    $('panel-admin').hidden = !esAdmin;

    if (esAdmin) {
        cargarUsuarios();
    }

    $('boton-nueva').hidden = usuario.rol === 'consulta';
    cerrarFormPost();
    cargarCategorias();
    cargarPosts();
}

function elegirPestana(nombre) {
    const esLogin = nombre === 'login';

    $('form-login').hidden = !esLogin;
    $('form-registro').hidden = esLogin;
    $('pestana-login').classList.toggle('activa', esLogin);
    $('pestana-registro').classList.toggle('activa', !esLogin);
    $('pestana-login').setAttribute('aria-selected', String(esLogin));
    $('pestana-registro').setAttribute('aria-selected', String(!esLogin));
}

// --- Acciones ---

async function enviar(formulario, accion) {
    const boton = formulario.querySelector('button[type="submit"]');
    boton.disabled = true;
    limpiarAviso();

    try {
        await accion(Object.fromEntries(new FormData(formulario)));
    } catch (error) {
        avisar(error.message, 'error');
    } finally {
        boton.disabled = false;
    }
}

async function entrar(datos) {
    const respuesta = await api('/login', { metodo: 'POST', datos });

    sessionStorage.setItem(CLAVE_TOKEN, respuesta.token);
    usuario = respuesta.user;
    $('form-login').reset();
    mostrarSesion();
}

async function registrar(datos) {
    const respuesta = await api('/register', { metodo: 'POST', datos });

    $('form-registro').reset();
    elegirPestana('login');
    avisar(respuesta.message);
}

async function salir() {
    try {
        await api('/logout', { metodo: 'POST' });
    } catch {
        // Aunque la API falle, la sesión se cierra en el navegador.
    }

    sessionStorage.removeItem(CLAVE_TOKEN);
    mostrarAcceso();
    avisar('Sesión cerrada.');
}

async function cargarUsuarios() {
    const filtro = $('filtro-usuarios').value;
    const lista = $('lista-usuarios');

    try {
        const respuesta = await api('/users' + (filtro === '' ? '' : `?activo=${filtro}`));

        lista.replaceChildren(...respuesta.data.map(filaDeUsuario));
        $('sin-usuarios').hidden = respuesta.data.length > 0;
    } catch (error) {
        avisar(error.message, 'error');
    }
}

function filaDeUsuario(u) {
    const fila = document.createElement('tr');

    const etiqueta = document.createElement('span');
    etiqueta.className = `etiqueta ${u.activo ? 'activo' : 'inactivo'}`;
    etiqueta.textContent = u.activo ? 'Activo' : 'Sin aprobar';

    const boton = document.createElement('button');
    boton.type = 'button';
    boton.textContent = u.activo ? 'Desactivar' : 'Aprobar';
    boton.addEventListener('click', () => cambiarEstado(u, boton));

    for (const contenido of [u.nombre, u.email, u.rol, etiqueta, u.id === usuario.id ? '' : boton]) {
        const celda = document.createElement('td');
        celda.append(contenido);
        fila.append(celda);
    }

    return fila;
}

async function cambiarEstado(u, boton) {
    boton.disabled = true;
    limpiarAviso();

    try {
        const accion = u.activo ? 'deactivate' : 'approve';
        const respuesta = await api(`/users/${u.id}/${accion}`, { metodo: 'PUT' });

        avisar(`${respuesta.message} (${u.email})`);
        await cargarUsuarios();
    } catch (error) {
        avisar(error.message, 'error');
        boton.disabled = false;
    }
}

// --- Publicaciones ---

const ETIQUETAS_VISIBILIDAD = {
    entrenadores: 'Solo entrenadores',
    todos: 'Todos los usuarios',
};

const mayuscula = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1);

function opcion(valor, texto) {
    const elemento = document.createElement('option');
    elemento.value = valor;
    elemento.textContent = texto;
    return elemento;
}

async function cargarCategorias() {
    try {
        const categorias = await api('/categories');
        const tipos = () => categorias.tipos.map((tipo) => opcion(tipo, mayuscula(tipo)));

        $('post-tipo').replaceChildren(...tipos());
        $('filtro-tipo').replaceChildren(opcion('', 'Todos los tipos'), ...tipos());
        $('post-visibilidad').replaceChildren(
            ...categorias.visibilidades.map((v) => opcion(v, ETIQUETAS_VISIBILIDAD[v] ?? v)),
        );
        $('post-equipo').replaceChildren(
            opcion('', 'Sin equipo'),
            ...categorias.equipos.map((e) => opcion(e.id, `${e.nombre} (${e.categoria})`)),
        );
    } catch (error) {
        avisar(error.message, 'error');
    }
}

async function cargarPosts() {
    const parametros = new URLSearchParams();
    const tipo = $('filtro-tipo').value;
    const busqueda = $('filtro-q').value.trim();

    if (tipo) {
        parametros.set('tipo', tipo);
    }
    if (busqueda) {
        parametros.set('q', busqueda);
    }

    try {
        const consulta = parametros.toString();
        const respuesta = await api('/posts' + (consulta ? `?${consulta}` : ''));

        liberarAdjuntos();
        $('lista-posts').replaceChildren(...respuesta.data.map(tarjetaDePost));
        $('sin-posts').hidden = respuesta.data.length > 0;
        $('mas-posts').hidden = respuesta.total <= respuesta.data.length;
        $('mas-posts').textContent = `Se muestran las ${respuesta.data.length} más recientes de ${respuesta.total}.`;
    } catch (error) {
        avisar(error.message, 'error');
    }
}

function tarjetaDePost(post) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'post';

    const titulo = document.createElement('h3');
    titulo.textContent = post.titulo;

    const tipo = document.createElement('span');
    tipo.className = 'etiqueta tipo';
    tipo.textContent = mayuscula(post.tipo);

    const detalles = [
        post.user?.nombre,
        new Date(post.created_at).toLocaleDateString('es-ES'),
        post.team?.nombre,
        post.temporada,
        ETIQUETAS_VISIBILIDAD[post.visibilidad],
    ].filter(Boolean);

    const meta = document.createElement('div');
    meta.className = 'post-meta';
    meta.append(tipo, ...detalles.map((texto) => {
        const dato = document.createElement('span');
        dato.textContent = texto;
        return dato;
    }));

    tarjeta.append(titulo, meta);

    if (post.contenido) {
        const contenido = document.createElement('p');
        contenido.className = 'post-contenido';
        contenido.textContent = post.contenido;
        tarjeta.append(contenido);
    }

    if (post.media?.length) {
        const adjuntos = document.createElement('div');
        adjuntos.className = 'adjuntos';
        adjuntos.append(...post.media.map((media) => elementoDeAdjunto(media, puedeGestionar(post))));
        tarjeta.append(adjuntos);
    }

    const comentarios = seccionDeComentarios(post);

    const verComentarios = document.createElement('button');
    verComentarios.type = 'button';
    verComentarios.setAttribute('aria-expanded', 'false');
    ponerContador(verComentarios, post.comentarios_count ?? 0);
    verComentarios.addEventListener('click', () => alternarComentarios(post, comentarios, verComentarios));

    const botones = document.createElement('div');
    botones.className = 'botones';
    botones.append(verComentarios);

    if (puedeGestionar(post)) {
        const editar = document.createElement('button');
        editar.type = 'button';
        editar.textContent = 'Editar';
        editar.addEventListener('click', () => abrirFormPost(post));

        const eliminar = document.createElement('button');
        eliminar.type = 'button';
        eliminar.textContent = 'Eliminar';
        eliminar.addEventListener('click', () => eliminarPost(post, eliminar));

        botones.append(editar, eliminar);
    }

    tarjeta.append(botones, comentarios);

    return tarjeta;
}

// --- Comentarios ---

function ponerContador(boton, total) {
    boton.dataset.total = total;
    boton.textContent = `Comentarios (${total})`;
}

function seccionDeComentarios(post) {
    const seccion = document.createElement('section');
    seccion.className = 'comentarios';
    seccion.hidden = true;

    const lista = document.createElement('ul');
    lista.className = 'lista-comentarios';

    const vacio = document.createElement('p');
    vacio.className = 'nota';
    vacio.textContent = 'Todavía no hay comentarios.';
    vacio.hidden = true;

    seccion.append(lista, vacio);

    // El rol de consulta puede leer, pero no comentar.
    if (usuario.rol !== 'consulta') {
        const formulario = document.createElement('form');
        formulario.className = 'form-comentario';
        formulario.noValidate = true;

        const texto = document.createElement('textarea');
        texto.name = 'contenido';
        texto.rows = 2;
        texto.maxLength = 2000;
        texto.placeholder = 'Escribe un comentario';
        texto.setAttribute('aria-label', 'Comentario');

        const enviarBoton = document.createElement('button');
        enviarBoton.type = 'submit';
        enviarBoton.className = 'principal';
        enviarBoton.textContent = 'Comentar';

        formulario.append(texto, enviarBoton);
        formulario.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviar(formulario, (datos) => comentar(post, seccion, datos));
        });
        seccion.append(formulario);
    }

    return seccion;
}

async function alternarComentarios(post, seccion, boton) {
    const abrir = seccion.hidden;

    seccion.hidden = !abrir;
    boton.setAttribute('aria-expanded', String(abrir));

    if (abrir) {
        await cargarComentarios(post, seccion);
    }
}

async function cargarComentarios(post, seccion) {
    try {
        const comentarios = await api(`/posts/${post.id}/comments`);
        const visibles = comentarios.filter((c) => !c.moderado).length;

        seccion.querySelector('.lista-comentarios').replaceChildren(
            ...comentarios.map((c) => elementoDeComentario(c, post, seccion)),
        );
        seccion.querySelector('.nota').hidden = comentarios.length > 0;
        // El botón "Comentarios (N)" es el primero de la fila de botones que va justo antes.
        ponerContador(seccion.previousElementSibling.firstElementChild, visibles);
    } catch (error) {
        avisar(error.message, 'error');
    }
}

function elementoDeComentario(comentario, post, seccion) {
    const elemento = document.createElement('li');
    elemento.className = comentario.moderado ? 'comentario oculto' : 'comentario';

    const cabecera = document.createElement('div');
    cabecera.className = 'post-meta';

    const autor = document.createElement('strong');
    autor.textContent = comentario.user?.nombre ?? 'Usuario';

    const fecha = document.createElement('span');
    fecha.textContent = new Date(comentario.created_at).toLocaleString('es-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    });

    cabecera.append(autor, fecha);

    if (comentario.moderado) {
        const marca = document.createElement('span');
        marca.className = 'etiqueta inactivo';
        marca.textContent = 'Oculto';
        cabecera.append(marca);
    }

    const texto = document.createElement('p');
    texto.textContent = comentario.contenido;

    elemento.append(cabecera, texto);

    const esAdmin = usuario.rol === 'admin';
    const acciones = document.createElement('div');
    acciones.className = 'acciones-comentario';

    if (esAdmin) {
        const moderar = document.createElement('button');
        moderar.type = 'button';
        moderar.textContent = comentario.moderado ? 'Mostrar' : 'Ocultar';
        moderar.addEventListener('click', () => moderarComentario(comentario, post, seccion, moderar));
        acciones.append(moderar);
    }

    if (esAdmin || comentario.user_id === usuario.id) {
        const eliminar = document.createElement('button');
        eliminar.type = 'button';
        eliminar.textContent = 'Eliminar';
        eliminar.addEventListener('click', () => eliminarComentario(comentario, post, seccion, eliminar));
        acciones.append(eliminar);
    }

    if (acciones.childElementCount) {
        elemento.append(acciones);
    }

    return elemento;
}

async function comentar(post, seccion, datos) {
    await api(`/posts/${post.id}/comments`, { metodo: 'POST', datos });

    seccion.querySelector('.form-comentario').reset();
    await cargarComentarios(post, seccion);
}

async function eliminarComentario(comentario, post, seccion, boton) {
    if (!confirm('¿Eliminar este comentario?')) {
        return;
    }

    boton.disabled = true;
    limpiarAviso();

    try {
        await api(`/comments/${comentario.id}`, { metodo: 'DELETE' });
        await cargarComentarios(post, seccion);
    } catch (error) {
        avisar(error.message, 'error');
        boton.disabled = false;
    }
}

async function moderarComentario(comentario, post, seccion, boton) {
    boton.disabled = true;
    limpiarAviso();

    try {
        await api(`/comments/${comentario.id}/moderate`, {
            metodo: 'PUT',
            datos: { moderado: !comentario.moderado },
        });
        await cargarComentarios(post, seccion);
    } catch (error) {
        avisar(error.message, 'error');
        boton.disabled = false;
    }
}

function puedeGestionar(post) {
    return usuario.rol === 'admin' || post.user_id === usuario.id;
}

// --- Adjuntos ---

function elementoDeAdjunto(media, gestionable) {
    const caja = document.createElement('div');
    caja.className = `adjunto ${media.tipo}`;

    if (media.tipo === 'youtube') {
        const video = document.createElement('iframe');
        video.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(media.youtube_id)}`;
        video.title = 'Vídeo de YouTube';
        video.loading = 'lazy';
        video.allow = 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        video.allowFullscreen = true;
        video.referrerPolicy = 'strict-origin-when-cross-origin';
        caja.append(video);
    } else if (media.tipo === 'imagen') {
        // Un <img> normal no envía el token, así que la imagen se descarga con él
        // y se muestra desde una URL local del navegador.
        const enlace = document.createElement('a');
        enlace.target = '_blank';
        enlace.rel = 'noopener';
        const imagen = document.createElement('img');
        imagen.alt = media.nombre_original ?? 'Imagen adjunta';
        enlace.append(imagen);
        caja.append(enlace);

        descargarAdjunto(media)
            .then((blob) => {
                const url = URL.createObjectURL(blob);
                urlsDeAdjuntos.push(url);
                imagen.src = url;
                enlace.href = url;
            })
            .catch(() => {
                imagen.alt = 'No se pudo cargar la imagen';
            });
    } else {
        const archivo = document.createElement('button');
        archivo.type = 'button';
        archivo.className = 'archivo';
        archivo.textContent = `${media.nombre_original} (${tamanoLegible(media.tamano)})`;
        archivo.addEventListener('click', () => guardarAdjunto(media, archivo));
        caja.append(archivo);
    }

    if (gestionable) {
        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'quitar';
        quitar.textContent = 'Quitar';
        quitar.addEventListener('click', () => quitarAdjunto(media, quitar));
        caja.append(quitar);
    }

    return caja;
}

function tamanoLegible(bytes) {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function guardarAdjunto(media, boton) {
    boton.disabled = true;

    try {
        const url = URL.createObjectURL(await descargarAdjunto(media));
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = media.nombre_original ?? 'archivo';
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
        avisar(error.message, 'error');
    } finally {
        boton.disabled = false;
    }
}

async function quitarAdjunto(media, boton) {
    if (!confirm('¿Quitar este adjunto?')) {
        return;
    }

    boton.disabled = true;
    limpiarAviso();

    try {
        const respuesta = await api(`/media/${media.id}`, { metodo: 'DELETE' });

        avisar(respuesta.message);
        await cargarPosts();
    } catch (error) {
        avisar(error.message, 'error');
        boton.disabled = false;
    }
}

// Sube los archivos y el enlace de una publicación ya guardada.
// Devuelve la lista de los que no se pudieron añadir, con el motivo.
async function subirAdjuntos(postId, archivos, enlace) {
    const fallos = [];

    for (const archivo of archivos) {
        if (archivo.size > MAX_BYTES_ADJUNTO) {
            fallos.push(`${archivo.name}: supera los 10 MB.`);
            continue;
        }

        const datos = new FormData();
        datos.append('post_id', postId);
        datos.append('archivo', archivo);

        try {
            await api('/media', { metodo: 'POST', datos });
        } catch (error) {
            fallos.push(`${archivo.name}: ${error.message}`);
        }
    }

    if (enlace) {
        const datos = new FormData();
        datos.append('post_id', postId);
        datos.append('url', enlace);

        try {
            await api('/media', { metodo: 'POST', datos });
        } catch (error) {
            fallos.push(`Enlace de YouTube: ${error.message}`);
        }
    }

    return fallos;
}

function liberarAdjuntos() {
    urlsDeAdjuntos.forEach((url) => URL.revokeObjectURL(url));
    urlsDeAdjuntos = [];
}

function abrirFormPost(post = null) {
    const formulario = $('form-post');

    postEditando = post;
    formulario.reset();
    limpiarAviso();

    if (post) {
        formulario.titulo.value = post.titulo;
        formulario.tipo.value = post.tipo;
        formulario.visibilidad.value = post.visibilidad;
        formulario.team_id.value = post.team_id ?? '';
        formulario.temporada.value = post.temporada ?? '';
        formulario.contenido.value = post.contenido ?? '';
    }

    $('titulo-form-post').textContent = post ? 'Editar publicación' : 'Nueva publicación';
    $('boton-guardar-post').textContent = post ? 'Guardar cambios' : 'Publicar';
    formulario.hidden = false;
    formulario.scrollIntoView({ block: 'nearest' });
    formulario.titulo.focus();
}

function cerrarFormPost() {
    postEditando = null;
    $('form-post').reset();
    $('form-post').hidden = true;
}

async function guardarPost(datos) {
    const { archivos: _archivos, enlace = '', ...campos } = datos;
    const archivos = [...$('form-post').archivos.files];
    const cuerpo = {
        ...campos,
        contenido: campos.contenido.trim() || null,
        temporada: campos.temporada.trim() || null,
        team_id: campos.team_id ? Number(campos.team_id) : null,
    };

    const post = postEditando
        ? await api(`/posts/${postEditando.id}`, { metodo: 'PUT', datos: cuerpo })
        : await api('/posts', { metodo: 'POST', datos: cuerpo });
    const mensaje = postEditando ? 'Publicación actualizada' : 'Publicación creada';
    const fallos = await subirAdjuntos(post.id, archivos, enlace.trim());

    cerrarFormPost();
    await cargarPosts();

    if (fallos.length) {
        avisar(`${mensaje}, pero no se pudo añadir:\n${fallos.join('\n')}`, 'error');
    } else {
        avisar(`${mensaje}.`);
    }
}

async function eliminarPost(post, boton) {
    if (!confirm(`¿Eliminar «${post.titulo}»?`)) {
        return;
    }

    boton.disabled = true;
    limpiarAviso();

    try {
        const respuesta = await api(`/posts/${post.id}`, { metodo: 'DELETE' });

        if (postEditando?.id === post.id) {
            cerrarFormPost();
        }
        avisar(respuesta.message);
        await cargarPosts();
    } catch (error) {
        avisar(error.message, 'error');
        boton.disabled = false;
    }
}

// --- Arranque ---

async function comprobarApi() {
    const estado = $('estado-api');

    try {
        await api('/health');
        estado.textContent = 'API conectada';
        estado.className = 'estado ok';
    } catch (error) {
        estado.textContent = error.estado === 0 ? 'Sin conexión con la API' : 'La API responde con errores';
        estado.className = 'estado error';
    }
}

async function recuperarSesion() {
    if (!sessionStorage.getItem(CLAVE_TOKEN)) {
        mostrarAcceso();
        return;
    }

    try {
        usuario = await api('/user');
        mostrarSesion();
    } catch {
        sessionStorage.removeItem(CLAVE_TOKEN);
        mostrarAcceso();
    }
}

$('pestana-login').addEventListener('click', () => elegirPestana('login'));
$('pestana-registro').addEventListener('click', () => elegirPestana('registro'));
$('boton-salir').addEventListener('click', salir);
$('filtro-usuarios').addEventListener('change', cargarUsuarios);

$('form-login').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(evento.target, entrar);
});

$('form-registro').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(evento.target, registrar);
});

$('boton-nueva').addEventListener('click', () => abrirFormPost());
$('boton-cancelar-post').addEventListener('click', cerrarFormPost);
$('filtro-tipo').addEventListener('change', cargarPosts);

let esperaBusqueda;
$('filtro-q').addEventListener('input', () => {
    clearTimeout(esperaBusqueda);
    esperaBusqueda = setTimeout(cargarPosts, 300);
});

$('form-post').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(evento.target, guardarPost);
});

comprobarApi();
recuperarSesion();
