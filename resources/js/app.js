import './bootstrap';

// Alpine is intentionally NOT imported/started here. Livewire 3 bundles its
// own Alpine build (with the $wire magic and other plugins registered) and
// auto-injects + starts it on every page automatically. Manually starting a
// second, separate Alpine instance from this file — Breeze's original
// default — raced with Livewire's own instance and left some elements
// processed by the plugin-less one, which is why `$wire` was undefined
// inside the check-in button's @click handler even though the code itself
// was correct.
