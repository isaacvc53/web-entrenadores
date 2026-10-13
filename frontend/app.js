// Dirección de la API del backend en local.
const API = 'http://127.0.0.1:8010/api';

const CLAVE_TOKEN = 'portal_token';

const $ = (id) => document.getElementById(id);

let usuario = null;
let postEditando = null;

// --- Llamadas a la API ---

async function api(ruta, { metodo = 'GET', datos } = {}) {
    const cabeceras = { Accept: 'application/json' };
    const token = sessionStorage.getItem(CLAVE_TOKEN);

    if (token) {
        cabeceras.Authorization = `Bearer ${token}`;
    }
    if (datos) {
        cabeceras['Content-Type'] = 'application/json';
    }

    let respuesta;
    try {
        respuesta = await fetch(API + ruta, {
            method: metodo,
            headers: cabeceras,
            body: datos ? JSON.stringify(datos) : undefined,
        });
    } catch {
        throw new ErrorApi(0, 'No se pudo conectar con la API. ¿Está arrancado el backend?');
    }

    const cuerpo = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        throw new ErrorApi(respuesta.status, mensajeDeError(respuesta.status, cuerpo));
    }

    return cuerpo;
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

    if (usuario.rol === 'admin' || post.user_id === usuario.id) {
        const editar = document.createElement('button');
        editar.type = 'button';
        editar.textContent = 'Editar';
        editar.addEventListener('click', () => abrirFormPost(post));

        const eliminar = document.createElement('button');
        eliminar.type = 'button';
        eliminar.textContent = 'Eliminar';
        eliminar.addEventListener('click', () => eliminarPost(post, eliminar));

        const botones = document.createElement('div');
        botones.className = 'botones';
        botones.append(editar, eliminar);
        tarjeta.append(botones);
    }

    return tarjeta;
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
    const cuerpo = {
        ...datos,
        contenido: datos.contenido.trim() || null,
        temporada: datos.temporada.trim() || null,
        team_id: datos.team_id ? Number(datos.team_id) : null,
    };

    if (postEditando) {
        await api(`/posts/${postEditando.id}`, { metodo: 'PUT', datos: cuerpo });
        avisar('Publicación actualizada.');
    } else {
        await api('/posts', { metodo: 'POST', datos: cuerpo });
        avisar('Publicación creada.');
    }

    cerrarFormPost();
    await cargarPosts();
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
