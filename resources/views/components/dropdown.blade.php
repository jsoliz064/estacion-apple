@props(['align' => 'right'])

<div x-data="{
    open: false,
    init() {
        // Configuración inicial
        this.$watch('open', (value) => {
            if (value) {
                this.$nextTick(() => this.positionDropdown());
                document.body.classList.add('overflow-y-auto');
            } else {
                document.body.classList.remove('overflow-y-auto');
            }
        });
    },
    positionDropdown() {
        if (!this.open) return;
        
        const button = this.$el.querySelector('button');
        const dropdown = this.$el.querySelector('[x-show=\'open\']');
        const buttonRect = button.getBoundingClientRect();
        const dropdownRect = dropdown.getBoundingClientRect();
        
        // Resetear estilos primero
        dropdown.style.top = '';
        dropdown.style.bottom = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.maxHeight = '';
        dropdown.style.position = '';
        dropdown.style.transform = '';
        
        // Calcular espacio disponible
        const viewportHeight = window.innerHeight;
        const spaceBelow = viewportHeight - buttonRect.bottom;
        const spaceAbove = buttonRect.top;
        const dropdownHeight = Math.min(dropdownRect.height, viewportHeight * 0.6);
        
        // Posicionamiento para móviles (pantallas pequeñas)
        if (window.innerWidth < 768) {
            dropdown.style.position = 'fixed';
            dropdown.style.width = '90vw';
            dropdown.style.maxHeight = '60vh';
            dropdown.style.overflowY = 'auto';
            
            // Centrar horizontalmente
            dropdown.style.left = '50%';
            dropdown.style.transform = 'translateX(-50%)';
            
            // Posicionar verticalmente
            if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
                // Abrir hacia arriba si hay más espacio arriba
                dropdown.style.bottom = `${window.innerHeight - buttonRect.top + 8}px`;
            } else {
                // Abrir hacia abajo por defecto
                dropdown.style.top = `${buttonRect.bottom + window.scrollY + 8}px`;
            }
        } 
        // Posicionamiento para desktop
        else {
            dropdown.style.position = 'absolute';
            dropdown.style.width = '14rem'; // w-56 equivalente
            
            // Posicionamiento vertical
            if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
                dropdown.style.bottom = '100%';
                dropdown.style.marginBottom = '0.25rem';
            } else {
                dropdown.style.top = '100%';
                dropdown.style.marginTop = '0.25rem';
            }
            
            // Posicionamiento horizontal
            if (this.$el.closest('.overflow-x-auto')) {
                dropdown.style.left = `${buttonRect.left}px`;
                dropdown.style.position = 'fixed';
            } else {
                if (this.align === 'right') {
                    dropdown.style.right = '0';
                } else {
                    dropdown.style.left = '0';
                }
            }
        }
    }
}"
x-init="init()"
@click.away="open = false"
@resize.window.debounce="if (open) positionDropdown()"
@scroll.window.debounce="if (open) positionDropdown()"
class="relative inline-block text-left"
:class="{ 'z-50': open }">
    <div>
        <button @click="open = !open" type="button"
            class="inline-flex justify-center w-full px-2 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
            aria-haspopup="true" :aria-expanded="open">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
            </svg>
        </button>
    </div>

    <div x-show="open" x-transition
        class="z-[9999] bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        style="display: none;">
        <div class="py-1 overflow-y-auto max-h-[60vh]">
            {{ $slot }}
        </div>
    </div>
</div>