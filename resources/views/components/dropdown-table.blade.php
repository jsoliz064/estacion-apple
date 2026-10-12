@props(['align' => 'right'])

<div x-data="{
    open: false,
    init() {
        this.$watch('open', (value) => {
            if (value) {
                this.$nextTick(() => this.positionDropdown());
                // Add scroll listener when dropdown opens
                window.addEventListener('scroll', this.handleScroll.bind(this), { passive: true });
            } else {
                // Remove scroll listener when dropdown closes
                window.removeEventListener('scroll', this.handleScroll.bind(this));
            }
        });
    },

    handleScroll() {
        if (this.open) {
            this.positionDropdown();
        }
    },
    positionDropdown() {
        const button = this.$el.querySelector('button');
        const dropdown = this.$el.querySelector('[x-show=\'open\']');
        if (!button || !dropdown) return;

        const buttonRect = button.getBoundingClientRect();
        const dropdownHeight = dropdown.scrollHeight;
        const viewportHeight = window.innerHeight;

        const spaceBelow = viewportHeight - buttonRect.bottom;
        const spaceAbove = buttonRect.top;
        const needsToOpenUpward = spaceBelow < dropdownHeight && spaceAbove > dropdownHeight;

        dropdown.style.top = '';
        dropdown.style.bottom = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.maxHeight = '';
        dropdown.style.transform = '';

        if (window.innerWidth < 640) {
            dropdown.style.position = 'fixed';
            dropdown.style.width = 'calc(100vw - 2rem)';
            dropdown.style.maxHeight = '60vh';
            dropdown.style.overflowY = 'auto';

            dropdown.style.left = '1rem';
            dropdown.style.right = '1rem';

            if (needsToOpenUpward) {
                dropdown.style.bottom = `${viewportHeight - buttonRect.top + 8}px`;
                dropdown.style.top = 'auto';
            } else {
                dropdown.style.top = `${buttonRect.bottom + 8}px`;
                dropdown.style.bottom = 'auto';
            }
        } else {
            dropdown.style.position = 'fixed';
            dropdown.style.width = '14rem'; // Same as w-56

            if (needsToOpenUpward) {
                dropdown.style.bottom = `${viewportHeight - buttonRect.top + 8}px`;
                dropdown.style.top = 'auto';
            } else {
                dropdown.style.top = `${buttonRect.bottom + 8}px`;
                dropdown.style.bottom = 'auto';
            }

            if (this.$el.closest('.overflow-x-auto')) {
                dropdown.style.left = `${buttonRect.left}px`;
            } else {
                if (this.align === 'right') {
                    dropdown.style.right = `${window.innerWidth - buttonRect.right}px`;
                    dropdown.style.left = 'auto';
                } else {
                    dropdown.style.left = `${buttonRect.left}px`;
                    dropdown.style.right = 'auto';
                }
            }
        }

        this.ensureVisibility(dropdown);
    },
    ensureVisibility(dropdown) {
        const dropdownRect = dropdown.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        // Adjust if going off bottom of screen
        if (dropdownRect.bottom > viewportHeight) {
            dropdown.style.maxHeight = `${viewportHeight - dropdownRect.top - 20}px`;
            dropdown.style.overflowY = 'auto';
        }

        // Adjust if going off right of screen
        if (dropdownRect.right > viewportWidth) {
            dropdown.style.right = '1rem';
            dropdown.style.left = 'auto';
        }

        // Adjust if going off left of screen
        if (dropdownRect.left < 0) {
            dropdown.style.left = '1rem';
            dropdown.style.right = 'auto';
        }
    }
}" @click.away="open = false" @resize.window.debounce="if (open) positionDropdown()"
    class="relative inline-block text-left">
    <div>
        <button @click="open = !open" type="button"
            class="inline-flex justify-center w-full px-2 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500"
            aria-haspopup="true" :aria-expanded="open">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path
                    d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
            </svg>
        </button>
    </div>

    <div x-show="open" x-transition
        class="fixed z-[9999] w-56 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        style="display: none;">
        <div class="py-1">
            {{ $slot }}
        </div>
    </div>
</div>
