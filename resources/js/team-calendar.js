import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

document.addEventListener('livewire:init', () => {
    const el = document.getElementById('team-calendar');

    if (el === null || el.dataset.fcInitialized) {
        return;
    }

    el.dataset.fcInitialized = '1';

    const wireEl = el.closest('[wire\\:id]');
    const wireId = wireEl?.getAttribute('wire:id');

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin],
        initialView: 'dayGridMonth',
        events: window.teamCalendarInitialEvents ?? [],
        datesSet(info) {
            if (!wireId) {
                return;
            }

            Livewire.find(wireId).call('loadEventsForRange', info.startStr, info.endStr);
        },
    });

    calendar.render();

    Livewire.on('calendar-events-updated', (payload) => {
        calendar.removeAllEvents();
        calendar.addEventSource(payload.events);
    });
});
