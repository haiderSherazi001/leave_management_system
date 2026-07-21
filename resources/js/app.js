import './bootstrap';

// Alpine is intentionally NOT imported/started here. Livewire 3 bundles its
// own Alpine build (with the $wire magic and other plugins registered) and
// auto-injects + starts it on every page automatically. Manually starting a
// second, separate Alpine instance from this file — Breeze's original
// default — raced with Livewire's own instance and left some elements
// processed by the plugin-less one, which is why `$wire` was undefined
// inside the check-in button's @click handler even though the code itself
// was correct.

// Shared across every admin CRUD screen (Holidays, Departments, Employees,
// Leave Types): each one dispatches 'form-opened' from its edit()/
// startCreate() methods, and marks its form's wrapping <x-card> with
// data-autofocus-form. One listener here handles all of them instead of
// duplicating the same scroll/focus JS in four separate Blade files.
document.addEventListener('livewire:init', () => {
    Livewire.on('form-opened', () => {
        requestAnimationFrame(() => {
            const form = document.querySelector('[data-autofocus-form]');

            if (! form) {
                return;
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'start' });

            form.querySelector('input, select, textarea')?.focus({ preventScroll: true });
        });
    });
});
