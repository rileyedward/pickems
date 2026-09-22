import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

// Page-level errors (e.g. "every game needs a final score") that don't belong
// to a form field are raised under these keys and shown as a toast instead.
const toastErrorKeys = ['week', 'user', 'picks'];

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('error', (event) => {
        const errors = event.detail.errors as Record<string, string>;

        toastErrorKeys
            .filter((key) => errors[key])
            .forEach((key) => toast.error(errors[key]));
    });
}
