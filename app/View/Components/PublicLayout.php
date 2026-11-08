<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * El layout de las paginas publicas (catalogo, garantia). El titulo, la
 * descripcion y la imagen van tambien a las etiquetas og:*: son la vista previa
 * que arma WhatsApp al compartir el link.
 */
class PublicLayout extends Component
{
    public function __construct(
        public ?string $titulo = null,
        public ?string $descripcion = null,
        public ?string $imagen = null,
    ) {
    }

    public function render(): View|Closure|string
    {
        return view('layouts.public-layout');
    }
}
