// La pistola USB de codigos de barras, escuchada en segundo plano.
//
// La pistola es un teclado: teclea el codigo muy rapido y aprieta Enter. Antes
// solo servia con el foco en el buscador; con el foco en el precio de una linea
// el IMEI caia en el precio, y sin foco se perdia. Esta escucha reconoce la
// rafaga por el tiempo entre teclas y la entrega al BUSCADOR de la pantalla (o
// del modal abierto) con escribirCodigo(): lo mismo que hace la camara, asi el
// servidor sigue teniendo un solo camino para las dos.
//
// El buscador es el contenedor con data-escaner-principal="<prioridad>": gana el
// visible y habilitado de mayor prioridad (en la compra, Repuestos y accesorios
// le gana a la busqueda de la tabla de equipos; si esta plegado, gana la tabla).
// Con un modal abierto solo cuenta el buscador del modal de mas arriba.
//
// - Si el foco esta en un campo de codigo que NO es el buscador (data-escaner:
//   IMEI, SKU, UPC del alta, IMEI de la permuta), la pistola escribe ahi, como
//   siempre: ese campo es el destino (decision del usuario).
// - Sin buscador visible (dashboard, modal de ficha), no se toca nada.
//
// Va en CAPTURA: en burbuja, el Enter del campo enfocado ya habria corrido. La
// primera tecla de una rafaga pasa (todavia no se sabe que es la pistola) y se
// guarda una foto del campo enfocado para deshacerla; las siguientes se tragan.
// Si al final no era la pistola (no llego Enter, o pocos caracteres), lo tragado
// se devuelve al campo: nadie que teclee rapido pierde letras.
//
// Viene de barcode-listener.js de otro sistema, que no servia tal cual: solo
// aceptaba numeros de 8 a 13 digitos (el IMEI tiene 15 y el SKU lleva letras),
// escuchaba en burbuja, iba en el x-data de cada pantalla y con un modal
// abierto no hacia nada.

// Entre teclas de una misma rafaga. La pistola teclea cada 5-20 ms; una persona
// casi nunca baja de 50 entre dos teclas distintas.
const HUECO_MS = 50;
// Silencio que da por cerrada una rafaga sin Enter (y que cubre el segundo Enter
// de las pistolas que mandan CR+LF).
const CIERRE_MS = 100;
// Mas corto que esto es tecleo, no una lectura.
const MIN_LARGO = 4;

// Mientras escribirCodigo() dispara sus eventos: su Enter sintetico no es la
// pistola. No se usa isTrusted para poder simularla desde la consola.
let entregando = false;
let iniciada = false;
let opciones = { pitido: () => {}, lectorAbierto: () => false };

let buffer = '';
let tragado = '';
let ultimaTecla = 0;
let foto = null;        // el campo enfocado al empezar la rafaga, para deshacer su primera tecla
let enJuego = false;    // la rafaga en curso tiene un buscador al que ir
let despachada = false; // ya se entrego esta rafaga (el segundo Enter de CR+LF se traga)
let reloj = null;

/**
 * Escribe un codigo en un campo como lo haria la pistola: el valor y Enter.
 *   - modo 'enter': el campo tiene su Enter, que lee $el.value y lo manda.
 *   - modo 'input': un wire:model comun (ficha, busqueda de tabla): input y
 *     change para que Livewire lo sincronice, y luego Enter.
 */
export function escribirCodigo(campo, codigo, modo) {
    entregando = true;

    try {
        campo.value = codigo;

        if (modo === 'input') {
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        }

        campo.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true, cancelable: true,
        }));
    } finally {
        entregando = false;
    }
}

export function iniciarPistola(config) {
    // Alpine puede reiniciar el componente que la arranca (wire:navigate): una
    // segunda escucha entregaria cada lectura dos veces.
    if (iniciada) {
        return;
    }

    iniciada = true;
    opciones = { ...opciones, ...config };
    window.addEventListener('keydown', alTeclear, true);

    if (import.meta.env.DEV) {
        // Para probar sin el aparato: simularPistola('356938035643809'). Las
        // teclas sinteticas no escriben en el campo, pero el ruteo es el real.
        window.simularPistola = async (codigo) => {
            for (const tecla of [...String(codigo), 'Enter']) {
                (document.activeElement ?? document.body).dispatchEvent(
                    new KeyboardEvent('keydown', { key: tecla, bubbles: true, cancelable: true }),
                );
                await new Promise((r) => setTimeout(r, 10));
            }
        };
    }
}

function alTeclear(e) {
    if (entregando || opciones.lectorAbierto() || e.ctrlKey || e.altKey || e.metaKey || e.isComposing) {
        return;
    }

    const tecla = e.key;

    // Shift, Tab, flechas, F1...: no son parte de un codigo.
    if (typeof tecla !== 'string' || (tecla.length > 1 && tecla !== 'Enter')) {
        return;
    }

    // Una tecla mantenida se repite cada ~30 ms y pareceria una rafaga.
    if (e.repeat) {
        cerrar();
        return;
    }

    const ahora = performance.now();
    const enRafaga = ahora - ultimaTecla <= HUECO_MS;
    ultimaTecla = ahora;

    if (tecla === 'Enter') {
        alEnter(e, enRafaga);
        return;
    }

    if (!enRafaga) {
        // Empieza otra rafaga: lo que quedo de la anterior vuelve a su campo
        // ANTES de que esta tecla escriba, asi no se desordena.
        cerrar();
        enJuego = !focoEnCampoDeCodigo() && buscador() !== null;
        foto = enJuego ? fotoDelFoco() : null;
        buffer = tecla;
    } else {
        buffer += tecla;

        if (enJuego) {
            e.preventDefault();
            tragado += tecla;
        }
    }

    programarCierre();
}

function alEnter(e, enRafaga) {
    if (despachada) {
        // El LF de una pistola que manda CR+LF.
        if (enRafaga) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        return;
    }

    if (!enJuego || !enRafaga || buffer.length < MIN_LARGO) {
        // Un Enter de verdad: lo tragado vuelve al campo antes de que su Enter
        // lo lea.
        cerrar();
        return;
    }

    const destino = buscador();

    if (!destino) {
        cerrar();
        return;
    }

    // Ni el Enter del campo enfocado ni un submit: la lectura no era para el.
    e.preventDefault();
    e.stopImmediatePropagation();

    const codigo = buffer;

    if (foto?.campo !== destino.campo) {
        deshacerFoto();
    }

    buffer = '';
    tragado = '';
    foto = null;
    despachada = true;

    escribirCodigo(destino.campo, codigo, destino.modo);
    opciones.pitido();
    // Sin moverle el foco: quien estaba en otro campo sigue ahi.
    destino.campo.scrollIntoView({ block: 'nearest' });

    programarCierre();
}

function cerrar() {
    clearTimeout(reloj);
    reloj = null;

    if (!despachada && tragado) {
        devolverTragado();
    }

    buffer = '';
    tragado = '';
    foto = null;
    enJuego = false;
    despachada = false;
}

function programarCierre() {
    clearTimeout(reloj);
    reloj = setTimeout(cerrar, CIERRE_MS);
}

// El modal abierto de mas arriba; si no hay, la pagina. Los modales de la casa
// van dentro de un @if, asi que uno cerrado ni siquiera esta en el DOM; los de
// Jetstream quedan con display:none. "Arriba" es la capa que le dio capaModal
// (app.js), no el orden del DOM: el cliente que se abre desde la reserva queda
// encima aunque se haya montado antes. Con la misma capa, gana el ultimo.
function contexto() {
    const capa = (m) => Number(m.style.zIndex) || 50;

    return [...document.querySelectorAll('.jetstream-modal')]
        .filter((m) => m.style.display !== 'none')
        .reduce((arriba, m) => (arriba === document || capa(m) >= capa(arriba) ? m : arriba), document);
}

function buscador() {
    let mejor = null;
    let prioridad = -Infinity;

    for (const contenedor of contexto().querySelectorAll('[data-escaner-principal]')) {
        const campo = contenedor.querySelector('input');

        // getClientRects vacio: el campo o un ancestro esta oculto (un
        // collapse-card plegado, un modal que se cierra).
        if (!campo || campo.disabled || campo.readOnly || campo.getClientRects().length === 0) {
            continue;
        }

        const p = Number(contenedor.dataset.escanerPrincipal) || 0;

        if (p > prioridad) {
            prioridad = p;
            mejor = { campo, modo: contenedor.querySelector('[data-modo]')?.dataset.modo ?? 'enter' };
        }
    }

    return mejor;
}

function focoEnCampoDeCodigo() {
    const contenedor = document.activeElement?.closest?.('[data-escaner]');

    return !!contenedor && !contenedor.hasAttribute('data-escaner-principal');
}

function esEditable(el) {
    return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && !el.readOnly && !el.disabled;
}

function fotoDelFoco() {
    const campo = document.activeElement;

    if (!esEditable(campo)) {
        return null;
    }

    let inicio = null;
    let fin = null;

    // number y email no tienen seleccion: leerla lanza.
    try {
        inicio = campo.selectionStart;
        fin = campo.selectionEnd;
    } catch {
        // sin seleccion
    }

    return { campo, valor: campo.value, inicio, fin };
}

// Quita del campo enfocado la primera tecla de la lectura, la que paso antes de
// saber que era la pistola, y avisa a wire:model.
function deshacerFoto() {
    const f = foto;

    if (!f?.campo?.isConnected || f.campo.value === f.valor) {
        return;
    }

    f.campo.value = f.valor;

    try {
        if (f.inicio !== null) {
            f.campo.setSelectionRange(f.inicio, f.fin);
        }
    } catch {
        // sin seleccion
    }

    f.campo.dispatchEvent(new Event('input', { bubbles: true }));
}

// No era la pistola: las teclas tragadas vuelven donde iban, en el cursor.
function devolverTragado() {
    const campo = foto?.campo;

    if (!campo?.isConnected || !esEditable(campo)) {
        return;
    }

    try {
        campo.setRangeText(tragado, campo.selectionStart, campo.selectionEnd, 'end');
    } catch {
        // number y email no aceptan setRangeText
        campo.value += tragado;
    }

    campo.dispatchEvent(new Event('input', { bubbles: true }));
}
