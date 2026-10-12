import './bootstrap';

// El lector de codigos por camara (components/escaner-overlay). Hay uno solo,
// en el layout, y cualquier campo lo abre con:
//
//   $dispatch('abrir-escaner', { destino: <input>, modo: 'enter'|'input', continuo: bool })
//
// Al leer un codigo hace lo MISMO que la pistola USB: escribe en el campo y
// "aprieta" Enter. Asi el servidor tiene un solo camino para los dos.
//   - modo 'enter': el campo tiene su Enter, que lee $el.value y lo manda.
//   - modo 'input': el campo es un wire:model comun (ficha, busqueda de tabla):
//     se disparan input y change para que Livewire lo sincronice, y luego Enter.
//
// Lo que no es reactivo (el campo, la camara, el bucle) vive fuera de Alpine:
// un elemento del DOM envuelto en un Proxy reactivo da "Illegal invocation".
let destino = null;
let stream = null;
let bucle = null;
let audio = null;
// El modulo de la camara (escaner.js), una vez cargado: apagar() lo necesita
// sin esperar un import().
let camara = null;
// A quien se le entregan las fotos (una funcion: no va en el estado de Alpine).
let alTomarFoto = null;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('escaner', () => ({
        abierto: false,
        cargando: false,
        error: '',
        modo: 'enter',
        continuo: false,
        hayLinterna: false,
        linternaEncendida: false,
        ultimo: '',
        ultimoEn: 0,
        lecturas: 0,

        init() {
            // Escape cierra el lector, no el modal de fondo: el x-on:keydown.escape.window
            // del modal se registra despues, y en captura este va primero.
            window.addEventListener('keydown', (e) => {
                if (this.abierto && e.key === 'Escape') {
                    e.stopImmediatePropagation();
                    this.cerrar();
                }
            }, true);

            // La camara no queda encendida en segundo plano ni al salir.
            document.addEventListener('visibilitychange', () => document.hidden && this.cerrar());
            window.addEventListener('pagehide', () => this.cerrar());
            document.addEventListener('livewire:navigating', () => this.cerrar());
        },

        async abrir(detalle) {
            if (!detalle?.destino) {
                return;
            }

            this.cerrar();
            destino = detalle.destino;
            this.modo = detalle.modo === 'input' ? 'input' : 'enter';
            this.continuo = !!detalle.continuo;
            this.error = '';
            this.ultimo = '';
            this.lecturas = 0;
            this.abierto = true;

            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.error = 'La cámara solo funciona con HTTPS. Use la pistola o escriba el código.';
                return;
            }

            this.cargando = true;
            // El AudioContext se crea dentro del toque del usuario: iOS no deja
            // crearlo despues.
            try {
                audio ??= new (window.AudioContext || window.webkitAudioContext)();
            } catch {
                audio = null;
            }

            try {
                camara = await import('./escaner.js');
                const [detector, s] = await Promise.all([camara.crearDetector(), camara.abrirCamara(this.$refs.video)]);

                if (!this.abierto) {
                    camara.detenerCamara();
                    return;
                }

                stream = s;
                this.hayLinterna = camara.puedeLinterna(stream);
                this.cargando = false;
                this.leer(detector);
            } catch (e) {
                this.cargando = false;
                this.error = e?.name === 'NotAllowedError'
                    ? 'No hay permiso para usar la cámara. Habilítelo en el navegador.'
                    : e?.name === 'NotFoundError'
                        ? 'Este dispositivo no tiene cámara.'
                        : 'No se pudo abrir la cámara.';
                this.apagar();
            }
        },

        leer(detector) {
            const video = this.$refs.video;

            const paso = async () => {
                if (!this.abierto || !stream) {
                    return;
                }

                if (video.readyState >= 2) {
                    try {
                        const codigos = await detector.detect(video);
                        const valor = codigos.map((c) => (c.rawValue || '').trim()).find((v) => v !== '');

                        if (valor) {
                            this.entregar(valor);
                        }
                    } catch {
                        // un cuadro que no se pudo leer: se sigue con el siguiente
                    }
                }

                if (this.abierto && stream) {
                    bucle = setTimeout(paso, 120);
                }
            };

            paso();
        },

        entregar(codigo) {
            // En modo continuo, la camara sigue viendo el mismo codigo: se
            // descarta la repeticion durante 2 s.
            if (codigo === this.ultimo && Date.now() - this.ultimoEn < 2000) {
                return;
            }

            this.ultimo = codigo;
            this.ultimoEn = Date.now();
            this.lecturas++;
            this.pitido();

            const campo = destino;

            if (!this.continuo) {
                this.cerrar();
            }

            if (!campo?.isConnected || campo.disabled) {
                return;
            }

            campo.value = codigo;

            if (this.modo === 'input') {
                campo.dispatchEvent(new Event('input', { bubbles: true }));
                campo.dispatchEvent(new Event('change', { bubbles: true }));
            }

            campo.dispatchEvent(new KeyboardEvent('keydown', {
                key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true, cancelable: true,
            }));
        },

        pitido() {
            try {
                navigator.vibrate?.(80);
            } catch {
                // sin vibracion
            }

            if (!audio) {
                return;
            }

            try {
                const osc = audio.createOscillator();
                const vol = audio.createGain();
                osc.frequency.value = 1400;
                vol.gain.value = 0.15;
                osc.connect(vol).connect(audio.destination);
                osc.start();
                osc.stop(audio.currentTime + 0.08);
            } catch {
                // sin sonido
            }
        },

        async alternarLinterna() {
            try {
                const { linterna } = await import('./escaner.js');
                await linterna(stream, !this.linternaEncendida);
                this.linternaEncendida = !this.linternaEncendida;
            } catch {
                this.hayLinterna = false;
            }
        },

        apagar() {
            clearTimeout(bucle);
            bucle = null;
            stream?.getTracks().forEach((t) => t.stop());
            stream = null;
            // Tambien el stream que abrirCamara() entrego y nunca llego a
            // `stream` (el detector fallo a medio abrir).
            camara?.detenerCamara();

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }

            this.hayLinterna = false;
            this.linternaEncendida = false;
        },

        cerrar() {
            this.apagar();
            this.abierto = false;
            this.cargando = false;
            destino = null;
        },
    }));

    // La camara de fotos del equipo (components/camara-fotos), una sola en el
    // layout como el lector. La abre cualquier boton con:
    //
    //   $dispatch('abrir-camara-fotos', { alTomar: (foto) => $wire.metodo(foto) })
    //
    // Queda abierta para sacar varias: cada disparo entrega un JPEG en base64 a
    // `alTomar`. Antes vivia dentro de cada modal de Livewire, y cada respuesta
    // del servidor le devolvia su class="hidden": por eso se cerraba tras cada
    // foto.
    window.Alpine.data('camaraFotos', () => ({
        abierto: false,
        cargando: false,
        error: '',
        tomadas: 0,
        ultima: '',
        destello: false,

        init() {
            window.addEventListener('keydown', (e) => {
                if (this.abierto && e.key === 'Escape') {
                    e.stopImmediatePropagation();
                    this.cerrar();
                }
            }, true);

            document.addEventListener('visibilitychange', () => document.hidden && this.cerrar());
            window.addEventListener('pagehide', () => this.cerrar());
            document.addEventListener('livewire:navigating', () => this.cerrar());
        },

        async abrir(detalle) {
            if (typeof detalle?.alTomar !== 'function') {
                return;
            }

            this.cerrar();
            alTomarFoto = detalle.alTomar;
            this.error = '';
            this.tomadas = 0;
            this.ultima = '';
            this.abierto = true;

            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.error = 'La cámara solo funciona con HTTPS.';
                return;
            }

            this.cargando = true;

            try {
                camara = await import('./escaner.js');
                await camara.abrirCamara(this.$refs.video);

                if (!this.abierto) {
                    camara.detenerCamara();
                    return;
                }

                this.cargando = false;
            } catch (e) {
                this.cargando = false;
                this.error = e?.name === 'NotAllowedError'
                    ? 'No hay permiso para usar la cámara. Habilítelo en el navegador.'
                    : e?.name === 'NotFoundError'
                        ? 'Este dispositivo no tiene cámara.'
                        : 'No se pudo abrir la cámara.';
                camara?.detenerCamara();
            }
        },

        disparar() {
            const video = this.$refs.video;

            if (this.cargando || this.error || !video?.videoWidth || !alTomarFoto) {
                return;
            }

            // Lado mayor a 1280 px como mucho: cada foto viaja en el estado de
            // Livewire, y una de 4000 px inflaba cada peticion del modal.
            const escala = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
            const canvas = this.$refs.canvas;
            canvas.width = Math.round(video.videoWidth * escala);
            canvas.height = Math.round(video.videoHeight * escala);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            const foto = canvas.toDataURL('image/jpeg', 0.85);
            this.ultima = foto;
            this.tomadas++;
            this.destello = true;
            setTimeout(() => (this.destello = false), 150);

            try {
                navigator.vibrate?.(40);
            } catch {
                // sin vibracion
            }

            alTomarFoto(foto);
        },

        cerrar() {
            if (this.abierto) {
                camara?.detenerCamara();
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }

            this.abierto = false;
            this.cargando = false;
            alTomarFoto = null;
        },
    }));

    // Una foto de la camara (data URL) sube como archivo con el upload de
    // Livewire: $wire.upload la deja en el temporal y el componente la recibe
    // en `propiedad`. Antes el base64 viajaba en el estado del componente y
    // cada peticion del modal arrastraba todas las fotos.
    window.subirFoto = async ($wire, propiedad, dataUrl) => {
        const avisar = () => {
            const msg = 'No se pudo subir la foto.';
            window.toastr ? window.toastr.error(msg) : window.alert(msg);
        };

        try {
            const blob = await (await fetch(dataUrl)).blob();
            const archivo = new File([blob], 'foto.jpg', { type: blob.type || 'image/jpeg' });
            $wire.upload(propiedad, archivo, () => {}, avisar);
        } catch {
            avisar();
        }
    };

    // El visor de las fotos de un equipo (ProductoFotosModal), que abre la
    // miniatura de las tablas. Solo mira: flechas, deslizar con el dedo, el
    // teclado y la tira de miniaturas. Cerrar lo decide el componente.
    window.Alpine.data('visorFotos', (fotos) => ({
        fotos,
        actual: 0,
        inicioX: null,

        init() {
            // El fondo no se desplaza debajo del visor.
            document.body.classList.add('overflow-hidden');
        },

        destroy() {
            document.body.classList.remove('overflow-hidden');
        },

        ir(i) {
            const n = this.fotos.length;
            this.actual = ((i % n) + n) % n;
            // querySelectorAll y no children: el primer hijo de la tira es el <template> del x-for.
            this.$refs.tira?.querySelectorAll('button')[this.actual]?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        },

        siguiente() {
            this.ir(this.actual + 1);
        },

        anterior() {
            this.ir(this.actual - 1);
        },

        tocar(e) {
            this.inicioX = e.changedTouches[0].clientX;
        },

        soltar(e) {
            if (this.inicioX === null) {
                return;
            }
            const dx = e.changedTouches[0].clientX - this.inicioX;
            this.inicioX = null;
            if (Math.abs(dx) > 50) {
                dx < 0 ? this.siguiente() : this.anterior();
            }
        },
    }));

    // Compartir el recibo PDF de una venta (detalle de la venta). En el celular
    // abre el menu de compartir con el PDF adjunto: el vendedor elige WhatsApp y
    // el contacto. Donde no se pueden compartir archivos (la PC), descarga el
    // PDF y abre WhatsApp Web con el numero del cliente, para adjuntarlo a mano.
    window.Alpine.data('compartirRecibo', (cfg) => ({
        generando: false,

        async compartir() {
            if (this.generando) {
                return;
            }

            this.generando = true;

            try {
                const resp = await fetch(cfg.url, { credentials: 'same-origin' });

                if (!resp.ok) {
                    throw new Error(resp.status);
                }

                const archivo = new File([await resp.blob()], cfg.archivo, { type: 'application/pdf' });

                if (navigator.canShare?.({ files: [archivo] })) {
                    try {
                        await navigator.share({ files: [archivo], text: cfg.texto });
                    } catch (e) {
                        // Cerrar el menu sin elegir no es un error.
                        if (e?.name !== 'AbortError') {
                            throw e;
                        }
                    }

                    return;
                }

                const enlace = document.createElement('a');
                enlace.href = URL.createObjectURL(archivo);
                enlace.download = cfg.archivo;
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
                setTimeout(() => URL.revokeObjectURL(enlace.href), 10000);

                const texto = encodeURIComponent(cfg.texto + ' (le adjunto el recibo en PDF)');
                window.open('https://wa.me/' + (cfg.telefono || '') + '?text=' + texto, '_blank');
            } catch {
                const msg = 'No se pudo generar el recibo.';
                window.toastr ? window.toastr.error(msg) : window.alert(msg);
            } finally {
                this.generando = false;
            }
        },
    }));
});
