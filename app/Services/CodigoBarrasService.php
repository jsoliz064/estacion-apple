<?php

namespace App\Services;

use App\Models\CodigoBarras;
use App\Models\CodigoLote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Unico escritor de codigos_lotes y codigos_barras. No abre transaccion: la
 * abre el componente, como en todo el repo.
 *
 * El codigo es un EAN-13 de USO INTERNO: GS1 reserva los prefijos 20-29 para
 * la tienda, asi que ningun fabricante los usa y un sticker nunca coincide con
 * el codigo de la caja de un producto. La pistola, la camara (ean_13) y
 * cualquier lector lo leen, y cabe en una etiqueta de 40 x 25 mm.
 */
class CodigoBarrasService
{
    public const PREFIJO = '20';
    public const MAX_CODIGOS = 500;
    public const MAX_COPIAS = 100;

    public function generarLote(int $cantidad, int $copias, ?string $nota = null): CodigoLote
    {
        if ($cantidad < 1 || $cantidad > self::MAX_CODIGOS) {
            throw ValidationException::withMessages(['cantidad' => 'Se generan de 1 a ' . self::MAX_CODIGOS . ' códigos por vez.']);
        }

        if ($copias < 1 || $copias > self::MAX_COPIAS) {
            throw ValidationException::withMessages(['copias' => 'Las copias por código van de 1 a ' . self::MAX_COPIAS . '.']);
        }

        $nota = trim((string) $nota);

        $lote = CodigoLote::create([
            'cantidad' => $cantidad,
            'copias' => $copias,
            'nota' => $nota === '' ? null : $nota,
            'user_id' => Auth::id(),
        ]);

        // Bloqueada: dos lotes a la vez no leen el mismo maximo. Si igual
        // chocaran, cb_secuencia_unica / cb_codigo_unico lo impiden.
        $secuencia = (int) CodigoBarras::query()->lockForUpdate()->max('secuencia');
        $codigos = [];

        // Un codigo que alguien ya tecleo a mano como UPC o SKU se salta: el
        // numero se pierde, pero ningun sticker sale repetido. No deberia pasar
        // nunca (nadie usa el prefijo 20), por eso no hace falta mas que esto.
        while (count($codigos) < $cantidad) {
            $candidatos = [];

            for ($i = count($codigos); $i < $cantidad; $i++) {
                $secuencia++;
                $candidatos[$secuencia] = self::codigoDe($secuencia);
            }

            $ocupados = CodigoBarras::consultaDuenos(array_values($candidatos))->pluck('codigo')->all();

            foreach ($candidatos as $s => $codigo) {
                if (!in_array($codigo, $ocupados, true)) {
                    $codigos[$s] = $codigo;
                }
            }
        }

        $ahora = now();
        $filas = [];

        foreach ($codigos as $s => $codigo) {
            $filas[] = [
                'lote_id' => $lote->id,
                'secuencia' => $s,
                'codigo' => $codigo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        CodigoBarras::insert($filas);

        return $lote;
    }

    /** El EAN-13 de un numero de secuencia: '20' + 10 digitos + verificador. */
    public static function codigoDe(int $secuencia): string
    {
        $doce = self::PREFIJO . str_pad((string) $secuencia, 10, '0', STR_PAD_LEFT);

        return $doce . self::digitoVerificador($doce);
    }

    /** El verificador de EAN-13: pesos 1 y 3 alternados desde la izquierda. */
    public static function digitoVerificador(string $doce): int
    {
        $suma = 0;

        foreach (str_split($doce) as $i => $digito) {
            $suma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $suma % 10) % 10;
    }
}
