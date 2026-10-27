// Dirección de la API.
// En local, la página (puerto 8020) y la API (puerto 8010) se arrancan por separado.
// En el servidor, la API está en la misma carpeta que la página: …/entrenadores/api.
const API = location.port === '8020'
    ? 'http://127.0.0.1:8010/api'
    : new URL('api', location.origin + location.pathname.replace(/[^/]*$/, '')).href;

const CLAVE_TOKEN = 'portal_token';

const $ = (id) => document.getElementById(id);

// Tamaño máximo de un adjunto; el backend aplica el mismo límite.
const MAX_BYTES_ADJUNTO = 10 * 1024 * 1024;

// Nombre que se muestra de cada rol.
const NOMBRES_ROL = {
    admin: 'Administrador',
    entrenador: 'Entrenador',
    consulta: 'Consulta',
};

let usuario = null;
let postEditando = null;
let catalogo = { categorias: [], tipos: [], visibilidades: [], equipos: [], temporadas: [] };
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

// --- Fotos de perfil ---

// Fotos ya descargadas, por id de usuario, para no pedir la misma foto una vez por publicación.
let fotosDePerfil = new Map();

function urlDeFoto(userId) {
    if (!fotosDePerfil.has(userId)) {
        const promesa = peticion(`/users/${userId}/photo`)
            .then((respuesta) => respuesta.blob())
            .then((blob) => URL.createObjectURL(blob));

        // Si falla, se olvida, para volver a intentarlo la próxima vez.
        promesa.catch(() => fotosDePerfil.delete(userId));
        fotosDePerfil.set(userId, promesa);
    }

    return fotosDePerfil.get(userId);
}

function olvidarFoto(userId) {
    fotosDePerfil.get(userId)?.then((url) => URL.revokeObjectURL(url)).catch(() => {});
    fotosDePerfil.delete(userId);
}

function olvidarFotos() {
    for (const userId of [...fotosDePerfil.keys()]) {
        olvidarFoto(userId);
    }
    fotosDePerfil = new Map();
}

function iniciales(nombre = '') {
    const palabras = nombre.trim().split(/\s+/).filter(Boolean);

    return palabras.slice(0, 2).map((p) => p[0].toUpperCase()).join('') || '?';
}

// Cada persona tiene siempre el mismo color de fondo, sacado de su nombre.
function colorDe(nombre = '') {
    let suma = 0;
    for (const letra of nombre) {
        suma = (suma * 31 + letra.codePointAt(0)) % 360;
    }

    return `hsl(${suma} 45% 38%)`;
}

// Círculo con la foto de la persona o, si no tiene, sus iniciales.
// "persona" es un usuario de la API: { id, nombre, tiene_foto }.
function avatar(persona, tamano = 36) {
    const circulo = document.createElement('span');
    circulo.className = 'avatar';
    circulo.style.setProperty('--tamano', `${tamano}px`);
    circulo.style.background = colorDe(persona?.nombre);
    circulo.textContent = iniciales(persona?.nombre);
    circulo.setAttribute('aria-hidden', 'true');

    if (persona?.tiene_foto) {
        urlDeFoto(persona.id)
            .then((url) => {
                const imagen = document.createElement('img');
                imagen.src = url;
                imagen.alt = '';
                circulo.replaceChildren(imagen);
            })
            .catch(() => {
                // Se quedan las iniciales.
            });
    }

    return circulo;
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

// Con la ventana de publicación abierta, el aviso sale dentro de ella; si no, quedaría tapado.
function avisar(texto, tipo = 'ok') {
    const aviso = $('dialogo-post').open ? $('aviso-post') : $('aviso');
    aviso.textContent = texto;
    aviso.className = `aviso ${tipo}`;
    aviso.hidden = false;
}

function limpiarAviso() {
    $('aviso').hidden = true;
    $('aviso-post').hidden = true;
}

// --- Vistas ---

function mostrarAcceso() {
    usuario = null;
    liberarAdjuntos();
    olvidarFotos();
    cerrarFormPost();
    $('lista-posts').replaceChildren();
    document.body.classList.remove('con-sesion');
    abrirMenu(false);
    $('vista-sesion').hidden = true;
    $('vista-acceso').hidden = false;
}

function mostrarSesion() {
    $('vista-acceso').hidden = true;
    $('vista-sesion').hidden = false;
    document.body.classList.add('con-sesion');

    pintarPerfil();

    const esAdmin = usuario.rol === 'admin';
    $('enlace-admin').hidden = !esAdmin;
    mostrarVista();

    if (esAdmin) {
        cargarUsuarios();
    }

    $('boton-nueva').hidden = usuario.rol === 'consulta';
    cerrarFormPost();
    cargarCategorias();
    cargarPosts();
}

// Secciones de la página, cada una con su dirección (#publicaciones, #perfil…).
// Así, al recargar se sigue en la misma sección y el botón "atrás" del navegador funciona.
const VISTAS = {
    publicaciones: 'vista-publicaciones',
    administracion: 'panel-admin',
    perfil: 'vista-perfil',
};

function mostrarVista() {
    let vista = location.hash.slice(1);

    if (!(vista in VISTAS) || (vista === 'administracion' && usuario?.rol !== 'admin')) {
        vista = 'publicaciones';
    }

    for (const [nombre, id] of Object.entries(VISTAS)) {
        $(id).hidden = nombre !== vista;
    }

    for (const enlace of document.querySelectorAll('#menu a')) {
        const activo = enlace.getAttribute('href') === `#${vista}`;
        enlace.classList.toggle('activa', activo);

        if (activo) {
            enlace.setAttribute('aria-current', 'page');
        } else {
            enlace.removeAttribute('aria-current');
        }
    }
}

// --- Mi perfil ---

function pintarPerfil() {
    $('dato-nombre').textContent = usuario.nombre;
    $('dato-email').textContent = usuario.email;
    $('dato-rol').textContent = NOMBRES_ROL[usuario.rol] ?? usuario.rol;

    $('avatar-perfil').replaceChildren(avatar(usuario, 96));
    $('avatar-menu').replaceChildren(avatar(usuario, 24));
    $('boton-quitar-foto').hidden = !usuario.tiene_foto;
}

async function cambiarFoto() {
    const entrada = $('input-foto');
    const archivo = entrada.files[0];
    entrada.value = '';

    if (!archivo) {
        return;
    }
    if (archivo.size > MAX_BYTES_ADJUNTO) {
        avisar('La foto supera los 10 MB.', 'error');
        return;
    }

    const datos = new FormData();
    datos.append('foto', archivo);

    await conBoton($('boton-foto'), async () => {
        const respuesta = await api('/user/photo', { metodo: 'POST', datos });
        await trasCambiarFoto(respuesta);
    });
}

async function quitarFoto() {
    if (!confirm('¿Quitar tu foto de perfil?')) {
        return;
    }

    await conBoton($('boton-quitar-foto'), async () => {
        const respuesta = await api('/user/photo', { metodo: 'DELETE' });
        await trasCambiarFoto(respuesta);
    });
}

// Tras cambiar la foto se olvida la anterior y se repinta todo donde sale.
async function trasCambiarFoto(respuesta) {
    usuario = respuesta.user;
    olvidarFoto(usuario.id);
    pintarPerfil();
    avisar(respuesta.message);
    await cargarPosts();
}

// En el móvil, el menú se abre y se cierra con el botón ☰.
function abrirMenu(abrir) {
    $('menu').classList.toggle('abierto', abrir);
    $('boton-menu').setAttribute('aria-expanded', String(abrir));
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
    history.replaceState(null, '', location.pathname);
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

    // Tu propio rol no se puede cambiar: se muestra como texto.
    const esYo = u.id === usuario.id;
    const rol = esYo ? (NOMBRES_ROL[u.rol] ?? u.rol) : selectorDeRol(u);

    // Nombre y, debajo, el email en pequeño: así la tabla cabe también en el móvil.
    const persona = document.createElement('div');
    const email = document.createElement('small');
    email.textContent = u.email;
    persona.append(u.nombre, email);

    for (const contenido of [persona, rol, etiqueta, esYo ? '' : boton]) {
        const celda = document.createElement('td');
        celda.append(contenido);
        fila.append(celda);
    }

    return fila;
}

function selectorDeRol(u) {
    const selector = document.createElement('select');
    selector.className = 'selector-rol';
    selector.setAttribute('aria-label', `Rol de ${u.nombre}`);

    for (const [valor, nombre] of Object.entries(NOMBRES_ROL)) {
        selector.add(new Option(nombre, valor, false, valor === u.rol));
    }

    selector.addEventListener('change', () => cambiarRol(u, selector));

    return selector;
}

async function cambiarRol(u, selector) {
    const nuevo = selector.value;

    // Dar o quitar el administrador se confirma, porque cambia lo que puede hacer esa persona.
    if ((nuevo === 'admin' || u.rol === 'admin')
        && !confirm(`¿Cambiar el rol de ${u.nombre} a «${NOMBRES_ROL[nuevo]}»?`)) {
        selector.value = u.rol;
        return;
    }

    selector.disabled = true;
    limpiarAviso();

    try {
        const respuesta = await api(`/users/${u.id}/role`, { metodo: 'PUT', datos: { rol: nuevo } });

        avisar(`${respuesta.message} (${u.email}: ${NOMBRES_ROL[nuevo]})`);
        await cargarUsuarios();
    } catch (error) {
        avisar(error.message, 'error');
        selector.value = u.rol;
        selector.disabled = false;
    }
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

// --- Administración: categorías, tipos y equipos ---

// Categorías y tipos se gestionan igual: nombre y orden. Solo cambia la ruta de la API.
const LISTAS_EDITABLES = {
    categorias: { clave: 'categorias', ruta: '/categories', lista: 'lista-categorias', formulario: 'form-categoria' },
    tipos: { clave: 'tipos', ruta: '/post-types', lista: 'lista-tipos', formulario: 'form-tipo' },
};

function elegirSeccionAdmin(id) {
    for (const pestana of document.querySelectorAll('.pestanas-admin .pestana')) {
        const activa = pestana.dataset.seccion === id;
        pestana.classList.toggle('activa', activa);
        pestana.setAttribute('aria-selected', String(activa));
        $(pestana.dataset.seccion).hidden = !activa;
    }
}

function pintarAdministracion() {
    for (const conf of Object.values(LISTAS_EDITABLES)) {
        const elementos = catalogo[conf.clave];
        $(conf.lista).replaceChildren(
            ...elementos.map((elemento, posicion) => filaEditable(conf, elemento, posicion, elementos.length)),
        );
    }

    pintarEquipos();
}

// Ejecuta una acción desde un botón: lo desactiva mientras tanto y muestra el error si falla.
async function conBoton(boton, accion) {
    boton.disabled = true;
    limpiarAviso();

    try {
        await accion();
    } catch (error) {
        avisar(error.message, 'error');
    } finally {
        boton.disabled = false;
    }
}

// Tras un cambio en el panel se recarga todo, porque nombres y desplegables cambian en toda la página.
async function recargarTrasCambio(mensaje) {
    await cargarCategorias();
    await cargarPosts();
    avisar(mensaje);
}

function filaEditable(conf, elemento, posicion, total) {
    const fila = document.createElement('form');
    fila.className = 'fila-editable';
    fila.noValidate = true;
    fila.dataset.id = elemento.id;

    const subir = botonDeMover('subir', '↑', `Subir «${elemento.nombre}»`, posicion === 0);
    subir.addEventListener('click', () => moverElemento(conf, posicion, -1, subir));

    const bajar = botonDeMover('bajar', '↓', `Bajar «${elemento.nombre}»`, posicion === total - 1);
    bajar.addEventListener('click', () => moverElemento(conf, posicion, 1, bajar));

    const nombre = document.createElement('input');
    nombre.type = 'text';
    nombre.name = 'nombre';
    nombre.maxLength = 50;
    nombre.value = elemento.nombre;
    nombre.setAttribute('aria-label', 'Nombre');

    const guardar = document.createElement('button');
    guardar.type = 'submit';
    guardar.textContent = 'Guardar';

    const borrar = document.createElement('button');
    borrar.type = 'button';
    borrar.textContent = 'Borrar';
    borrar.addEventListener('click', () => {
        if (confirm(`¿Borrar «${elemento.nombre}»?`)) {
            conBoton(borrar, async () => {
                const respuesta = await api(`${conf.ruta}/${elemento.id}`, { metodo: 'DELETE' });
                await recargarTrasCambio(respuesta.message);
            });
        }
    });

    fila.append(subir, bajar, nombre, guardar, borrar);
    fila.addEventListener('submit', (evento) => {
        evento.preventDefault();
        enviar(fila, async (datos) => {
            await api(`${conf.ruta}/${elemento.id}`, {
                metodo: 'PUT',
                datos: { nombre: datos.nombre.trim() },
            });
            await recargarTrasCambio(`«${datos.nombre.trim()}» guardado.`);
        });
    });

    return fila;
}

function botonDeMover(clase, flecha, etiqueta, desactivado) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = `mover ${clase}`;
    boton.textContent = flecha;
    boton.title = etiqueta;
    boton.setAttribute('aria-label', etiqueta);
    boton.disabled = desactivado;
    return boton;
}

// Intercambia un elemento con el de arriba (-1) o el de abajo (+1) y envía la lista completa ordenada.
async function moverElemento(conf, posicion, desplazamiento, boton) {
    const ids = catalogo[conf.clave].map((e) => e.id);
    const destino = posicion + desplazamiento;
    const movido = ids[posicion];

    [ids[posicion], ids[destino]] = [ids[destino], ids[posicion]];

    await conBoton(boton, async () => {
        await api(`${conf.ruta}/order`, { metodo: 'PUT', datos: { ids } });
        await cargarCategorias();

        // Tras repintar la lista, el foco vuelve a la misma flecha del elemento movido,
        // para poder seguir moviéndolo con el teclado.
        const fila = $(conf.lista).querySelector(`[data-id="${movido}"]`);
        const flecha = fila?.querySelector(desplazamiento < 0 ? '.subir' : '.bajar');
        (flecha && !flecha.disabled ? flecha : fila?.querySelector('input'))?.focus();
    });
}

async function crearElementoEditable(conf, datos) {
    const nombre = datos.nombre.trim();

    await api(conf.ruta, { metodo: 'POST', datos: { nombre } });
    $(conf.formulario).reset();
    await recargarTrasCambio(`«${nombre}» añadido.`);
}

// La temporada siguiente a "2026-27" es "2027-28", y a "2026-2027", "2027-2028".
// Si no tiene ninguno de esos formatos, no se propone nada.
function temporadaSiguiente(temporada) {
    const partes = /^(\d{4})-(\d{2}|\d{4})$/.exec(temporada ?? '');

    if (!partes) {
        return '';
    }

    const inicio = Number(partes[1]) + 1;
    const fin = partes[2].length === 4
        ? String(inicio + 1)
        : String((inicio + 1) % 100).padStart(2, '0');

    return `${inicio}-${fin}`;
}

// Temporada que debe mostrar el filtro tras recargar, por ejemplo la que se acaba de crear.
let temporadaAMostrar = null;

function pintarEquipos() {
    const temporadas = [...new Set(catalogo.equipos.map((e) => e.temporada))];
    const filtro = $('filtro-temporada-equipos');
    const habiaFiltro = filtro.options.length > 0;

    rellenarDesplegable(filtro, [...temporadas.map((t) => opcion(t, t)), opcion('', 'Todas')]);

    if (temporadaAMostrar !== null && temporadas.includes(temporadaAMostrar)) {
        filtro.value = temporadaAMostrar;
    } else if (!habiaFiltro && temporadas.length) {
        // La primera vez se muestra la temporada más reciente.
        filtro.value = temporadas[0];
    }
    temporadaAMostrar = null;

    const categorias = () => catalogo.categorias.map((c) => opcion(c.id, c.nombre));
    const filtroCategoria = $('filtro-categoria-equipos');
    rellenarDesplegable(filtroCategoria, [opcion('', 'Todas'), ...categorias()]);

    const visibles = catalogo.equipos.filter((e) =>
        (filtro.value === '' || e.temporada === filtro.value)
        && (filtroCategoria.value === '' || String(e.category_id) === filtroCategoria.value));

    $('lista-equipos').replaceChildren(...filasDeEquiposAgrupadas(visibles));
    $('lista-equipos').closest('table').classList.toggle('con-temporada', filtro.value === '');
    $('sin-equipos').hidden = visibles.length > 0;

    rellenarDesplegable($('nuevo-equipo-categoria'), categorias());
    rellenarDesplegable($('copiar-desde'), temporadas.map((t) => opcion(t, t)));

    const formEquipo = $('form-equipo');
    if (!formEquipo.temporada.value) {
        formEquipo.temporada.value = filtro.value || temporadas[0] || '';
    }

    const formCopiar = $('form-copiar-temporada');
    formCopiar.hasta.placeholder = temporadaSiguiente(formCopiar.desde.value) || 'Temporada nueva';
}

// Equipo que se está editando; solo se edita uno a la vez.
let equipoEditando = null;

// Agrupa los equipos por categoría, en el orden que decide el administrador,
// y dentro de cada una por temporada (la más reciente primero) y nombre.
function filasDeEquiposAgrupadas(equipos) {
    const posicion = new Map(catalogo.categorias.map((c, i) => [c.id, i]));
    const ordenados = [...equipos].sort((a, b) =>
        (posicion.get(a.category_id) ?? Infinity) - (posicion.get(b.category_id) ?? Infinity)
        || b.temporada.localeCompare(a.temporada)
        || a.nombre.localeCompare(b.nombre, 'es', { numeric: true }));

    const filas = [];
    let categoriaActual;

    for (const equipo of ordenados) {
        if (equipo.category_id !== categoriaActual) {
            categoriaActual = equipo.category_id;
            const total = ordenados.filter((e) => e.category_id === categoriaActual).length;
            filas.push(filaDeGrupo(`${equipo.category?.nombre ?? 'Sin categoría'} (${total})`));
        }

        filas.push(equipo.id === equipoEditando ? filaEditandoEquipo(equipo) : filaDeEquipo(equipo));
    }

    return filas;
}

function filaDeGrupo(titulo) {
    const fila = document.createElement('tr');
    fila.className = 'grupo';

    const celda = document.createElement('th');
    celda.colSpan = 3;
    celda.scope = 'colgroup';
    celda.textContent = titulo;

    fila.append(celda);
    return fila;
}

function filaDeEquipo(equipo) {
    const fila = document.createElement('tr');
    fila.dataset.id = equipo.id;

    const editar = document.createElement('button');
    editar.type = 'button';
    editar.className = 'editar';
    editar.textContent = 'Editar';
    editar.setAttribute('aria-label', `Editar «${equipo.nombre}»`);
    editar.addEventListener('click', () => {
        equipoEditando = equipo.id;
        pintarEquipos();
        $('lista-equipos').querySelector(`[data-id="${equipo.id}"] input`)?.focus();
    });

    const borrar = document.createElement('button');
    borrar.type = 'button';
    borrar.textContent = 'Borrar';
    borrar.setAttribute('aria-label', `Borrar «${equipo.nombre}»`);
    borrar.addEventListener('click', () => {
        if (confirm(`¿Borrar el equipo «${equipo.nombre}» (${equipo.temporada})?`)) {
            conBoton(borrar, async () => {
                const respuesta = await api(`/teams/${equipo.id}`, { metodo: 'DELETE' });
                await recargarTrasCambio(respuesta.message);
            });
        }
    });

    const acciones = document.createElement('div');
    acciones.className = 'botones';
    acciones.append(editar, borrar);

    for (const contenido of [equipo.nombre, equipo.temporada, acciones]) {
        const celda = document.createElement('td');
        celda.append(contenido);
        fila.append(celda);
    }

    return fila;
}

function filaEditandoEquipo(equipo) {
    const fila = document.createElement('tr');
    fila.className = 'editando';
    fila.dataset.id = equipo.id;

    const formulario = document.createElement('form');
    formulario.className = 'fila-nueva';
    formulario.noValidate = true;

    const nombre = document.createElement('input');
    nombre.type = 'text';
    nombre.name = 'nombre';
    nombre.maxLength = 100;
    nombre.value = equipo.nombre;
    nombre.setAttribute('aria-label', 'Nombre del equipo');

    const categoria = document.createElement('select');
    categoria.name = 'category_id';
    categoria.setAttribute('aria-label', 'Categoría');
    categoria.append(...catalogo.categorias.map((c) => opcion(c.id, c.nombre)));
    categoria.value = equipo.category_id;

    const temporada = document.createElement('input');
    temporada.type = 'text';
    temporada.name = 'temporada';
    temporada.maxLength = 20;
    temporada.value = equipo.temporada;
    temporada.setAttribute('aria-label', 'Temporada');

    const guardar = document.createElement('button');
    guardar.type = 'submit';
    guardar.className = 'principal';
    guardar.textContent = 'Guardar';

    const cancelar = document.createElement('button');
    cancelar.type = 'button';
    cancelar.textContent = 'Cancelar';
    cancelar.addEventListener('click', terminarEdicionDeEquipo);

    const botones = document.createElement('div');
    botones.className = 'botones';
    botones.append(guardar, cancelar);

    formulario.append(nombre, categoria, temporada, botones);
    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        enviar(formulario, async (datos) => {
            await api(`/teams/${equipo.id}`, {
                metodo: 'PUT',
                datos: {
                    nombre: datos.nombre.trim(),
                    category_id: Number(datos.category_id),
                    temporada: datos.temporada.trim(),
                },
            });
            equipoEditando = null;
            await recargarTrasCambio(`Equipo «${datos.nombre.trim()}» guardado.`);
        });
    });

    // Escape cancela la edición.
    formulario.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            terminarEdicionDeEquipo();
        }
    });

    const celda = document.createElement('td');
    celda.colSpan = 3;
    celda.append(formulario);
    fila.append(celda);

    return fila;
}

function terminarEdicionDeEquipo() {
    const id = equipoEditando;

    equipoEditando = null;
    pintarEquipos();
    $('lista-equipos').querySelector(`[data-id="${id}"] .editar`)?.focus();
}

async function crearEquipo(datos) {
    const cuerpo = {
        nombre: datos.nombre.trim(),
        category_id: Number(datos.category_id),
        temporada: datos.temporada.trim(),
    };

    await api('/teams', { metodo: 'POST', datos: cuerpo });

    temporadaAMostrar = cuerpo.temporada;
    const formulario = $('form-equipo');
    formulario.nombre.value = '';
    formulario.nombre.focus();
    await recargarTrasCambio(`Equipo «${cuerpo.nombre}» añadido a ${cuerpo.temporada}.`);
}

async function copiarTemporada(datos) {
    const formulario = $('form-copiar-temporada');
    const hasta = datos.hasta.trim() || temporadaSiguiente(datos.desde);
    const respuesta = await api('/teams/copy-season', { metodo: 'POST', datos: { desde: datos.desde, hasta } });

    formulario.hasta.value = '';
    temporadaAMostrar = hasta;
    await recargarTrasCambio(respuesta.message);
}

// --- Publicaciones ---

const ETIQUETAS_VISIBILIDAD = {
    entrenadores: 'Solo entrenadores',
    todos: 'Todos los usuarios',
};

function opcion(valor, texto) {
    const elemento = document.createElement('option');
    elemento.value = valor;
    elemento.textContent = texto;
    return elemento;
}

// Rellena un desplegable conservando la opción elegida, si sigue existiendo.
function rellenarDesplegable(desplegable, opciones) {
    const elegida = desplegable.value;

    desplegable.replaceChildren(...opciones);

    if ([...desplegable.options].some((o) => o.value === elegida)) {
        desplegable.value = elegida;
    }
}

// Opciones de equipo agrupadas por temporada, de la más reciente a la más antigua.
function opcionesDeEquipos(equipos) {
    const porTemporada = new Map();

    for (const equipo of equipos) {
        porTemporada.set(equipo.temporada, [...(porTemporada.get(equipo.temporada) ?? []), equipo]);
    }

    return [...porTemporada].map(([temporada, lista]) => {
        const grupo = document.createElement('optgroup');
        grupo.label = temporada;
        grupo.append(...lista.map((e) => opcion(e.id, `${e.nombre} (${e.category?.nombre ?? 'sin categoría'})`)));
        return grupo;
    });
}

// Carga categorías, tipos, visibilidades y equipos, y rellena todos los desplegables.
async function cargarCategorias() {
    try {
        catalogo = await api('/categories');

        const tipos = () => catalogo.tipos.map((t) => opcion(t.id, t.nombre));

        rellenarDesplegable($('post-tipo'), tipos());
        rellenarDesplegable($('filtro-tipo'), [opcion('', 'Todos los tipos'), ...tipos()]);
        rellenarDesplegable(
            $('post-visibilidad'),
            catalogo.visibilidades.map((v) => opcion(v, ETIQUETAS_VISIBILIDAD[v] ?? v)),
        );
        rellenarDesplegable($('post-equipo'), [opcion('', 'Sin equipo'), ...opcionesDeEquipos(catalogo.equipos)]);
        rellenarDesplegable($('filtro-equipo'), [opcion('', 'Todos los equipos'), ...opcionesDeEquipos(catalogo.equipos)]);

        rellenarDesplegable(
            $('filtro-temporada'),
            [opcion('', 'Todas las temporadas'), ...catalogo.temporadas.map((t) => opcion(t, t))],
        );

        if (usuario?.rol === 'admin') {
            pintarAdministracion();
        }
    } catch (error) {
        avisar(error.message, 'error');
    }
}

async function cargarPosts() {
    const parametros = new URLSearchParams();
    const filtros = {
        type_id: $('filtro-tipo').value,
        team_id: $('filtro-equipo').value,
        temporada: $('filtro-temporada').value,
        q: $('filtro-q').value.trim(),
    };

    for (const [nombre, valor] of Object.entries(filtros)) {
        if (valor) {
            parametros.set(nombre, valor);
        }
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

    // Arriba, quién la ha publicado y cuándo.
    const nombreAutor = document.createElement('strong');
    nombreAutor.textContent = post.user?.nombre ?? 'Usuario';

    const fecha = document.createElement('span');
    fecha.className = 'fecha';
    fecha.textContent = new Date(post.created_at).toLocaleDateString('es-ES', { dateStyle: 'long' });

    const textoAutor = document.createElement('div');
    textoAutor.append(nombreAutor, fecha);

    const autor = document.createElement('div');
    autor.className = 'autor';
    autor.append(avatar(post.user, 36), textoAutor);

    const titulo = document.createElement('h3');
    titulo.textContent = post.titulo;

    const tipo = document.createElement('span');
    tipo.className = 'etiqueta tipo';
    tipo.textContent = post.type?.nombre ?? 'Sin tipo';

    const detalles = [
        post.team && `${post.team.nombre} (${post.team.category?.nombre ?? 'sin categoría'})`,
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

    tarjeta.append(autor, titulo, meta);

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

    cabecera.append(avatar(comentario.user, 28), autor, fecha);

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

        // Algunos vídeos no se pueden ver fuera de YouTube porque su dueño lo impide,
        // y el botón del propio reproductor no siempre abre la página. Este enlace sí.
        const enlace = document.createElement('a');
        enlace.className = 'ver-en-youtube';
        enlace.href = `https://www.youtube.com/watch?v=${encodeURIComponent(media.youtube_id)}`;
        enlace.target = '_blank';
        enlace.rel = 'noopener noreferrer';
        enlace.textContent = 'Ver en YouTube';

        caja.append(video, enlace);
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
        formulario.type_id.value = post.type_id;
        formulario.visibilidad.value = post.visibilidad;
        formulario.team_id.value = post.team_id ?? '';
        formulario.temporada.value = post.temporada ?? '';
        formulario.contenido.value = post.contenido ?? '';
    }

    $('titulo-form-post').textContent = post ? 'Editar publicación' : 'Nueva publicación';
    $('boton-guardar-post').textContent = post ? 'Guardar cambios' : 'Publicar';
    $('dialogo-post').showModal();
    formulario.titulo.focus();
}

function cerrarFormPost() {
    if ($('dialogo-post').open) {
        $('dialogo-post').close();
    }
}

// Al cerrar la ventana, sea con un botón o con la tecla Escape, se olvida lo escrito.
function alCerrarFormPost() {
    postEditando = null;
    $('form-post').reset();
    $('aviso-post').hidden = true;
}

// Al elegir equipo, si la temporada está vacía se rellena con la del equipo.
function alElegirEquipo() {
    const formulario = $('form-post');
    const equipo = catalogo.equipos.find((e) => String(e.id) === formulario.team_id.value);

    if (equipo && !formulario.temporada.value.trim()) {
        formulario.temporada.value = equipo.temporada;
    }
}

async function guardarPost(datos) {
    const { archivos: _archivos, enlace = '', ...campos } = datos;
    const archivos = [...$('form-post').archivos.files];
    const cuerpo = {
        ...campos,
        contenido: campos.contenido.trim() || null,
        temporada: campos.temporada.trim() || null,
        type_id: Number(campos.type_id),
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
        // Si todo va bien no hace falta decir nada.
        estado.hidden = true;
    } catch (error) {
        estado.hidden = false;
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

window.addEventListener('hashchange', () => {
    limpiarAviso();
    abrirMenu(false);
    mostrarVista();
    window.scrollTo(0, 0);
});

$('boton-foto').addEventListener('click', () => $('input-foto').click());
$('input-foto').addEventListener('change', cambiarFoto);
$('boton-quitar-foto').addEventListener('click', quitarFoto);

$('boton-menu').addEventListener('click',() => abrirMenu(!$('menu').classList.contains('abierto')));

// Escape cierra el menú del móvil y devuelve el foco a su botón.
$('menu').addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape') {
        abrirMenu(false);
        $('boton-menu').focus();
    }
});

for (const pestana of document.querySelectorAll('.pestanas-admin .pestana')) {
    pestana.addEventListener('click', () => elegirSeccionAdmin(pestana.dataset.seccion));
}

for (const conf of Object.values(LISTAS_EDITABLES)) {
    $(conf.formulario).addEventListener('submit', (evento) => {
        evento.preventDefault();
        enviar(evento.target, (datos) => crearElementoEditable(conf, datos));
    });
}

$('filtro-temporada-equipos').addEventListener('change', pintarEquipos);
$('filtro-categoria-equipos').addEventListener('change', pintarEquipos);
$('copiar-desde').addEventListener('change', pintarEquipos);

$('form-equipo').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(evento.target, crearEquipo);
});

$('form-copiar-temporada').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(evento.target, copiarTemporada);
});

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
$('boton-cerrar-post').addEventListener('click', cerrarFormPost);
$('dialogo-post').addEventListener('close', alCerrarFormPost);
$('post-equipo').addEventListener('change', alElegirEquipo);

for (const filtro of ['filtro-tipo', 'filtro-equipo', 'filtro-temporada']) {
    $(filtro).addEventListener('change', cargarPosts);
}

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
