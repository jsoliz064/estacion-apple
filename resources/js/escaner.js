// El lector de codigos de barras por camara. Se carga con import() recien al
// abrir el lector (ver app.js): nadie mas paga su peso.
//
// Usa el BarcodeDetector NATIVO donde existe (Chrome de Android: rapido y sin
// descargar nada) y, donde no (Safari de iOS, Firefox), el de zxing-wasm. El
// .wasm se sirve desde public/build y no desde el CDN por defecto de la
// libreria: el sistema no debe depender de un tercero para vender.

const FORMATOS = [
    'code_128', // el IMEI de la caja y casi todas las etiquetas internas
    'ean_13', 'ean_8', 'upc_a', 'upc_e', // el codigo de barras del fabricante
    'code_39', 'itf', 'qr_code',
];

let detector = null;

// La UNICA camara encendida de la pagina. La usan el lector y la camara de
// fotos (components/camara-fotos), y abrir una apaga la anterior: antes cada
// una manejaba su stream, y uno perdido (el lector que fallaba a medio abrir)
// dejaba la camara tomada y las fotos en pantalla negra.
let activo = null;

export function detenerCamara() {
    activo?.getTracks().forEach((t) => t.stop());
    activo = null;
}

export async function crearDetector() {
    if (detector) {
        return detector;
    }

    if ('BarcodeDetector' in window) {
        try {
            const soportados = await window.BarcodeDetector.getSupportedFormats();
            const formats = FORMATOS.filter((f) => soportados.includes(f));

            // Hay Chrome de escritorio que expone la API sin ningun formato.
            if (formats.includes('code_128') && formats.includes('ean_13')) {
                detector = new window.BarcodeDetector({ formats });
                return detector;
            }
        } catch {
            // cae al de zxing
        }
    }

    const [{ BarcodeDetector, prepareZXingModule }, { default: wasmUrl }] = await Promise.all([
        import('barcode-detector/ponyfill'),
        import('zxing-wasm/reader/zxing_reader.wasm?url'),
    ]);

    prepareZXingModule({
        overrides: {
            locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
        },
    });

    detector = new BarcodeDetector({ formats: FORMATOS });
    return detector;
}

export async function abrirCamara(video) {
    detenerCamara();

    const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1280 },
            height: { ideal: 720 },
        },
    });

    // Se registra antes del play(): si el play() falla, la camara no queda
    // encendida sin dueno. El play() explicito es lo que evita la pantalla
    // negra: el autoplay solo no siempre arranca.
    activo = stream;
    video.srcObject = stream;
    await video.play();

    return stream;
}

export function puedeLinterna(stream) {
    const track = stream?.getVideoTracks()[0];
    return !!track?.getCapabilities?.().torch;
}

export async function linterna(stream, encendida) {
    const track = stream?.getVideoTracks()[0];
    await track?.applyConstraints({ advanced: [{ torch: encendida }] });
}
