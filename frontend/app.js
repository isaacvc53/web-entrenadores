// Dirección de la API del backend en local.
const API = 'http://127.0.0.1:8010/api';

const CLAVE_TOKEN = 'portal_token';

const $ = (id) => document.getElementById(id);

let usuario = null;

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

comprobarApi();
recuperarSesion();
