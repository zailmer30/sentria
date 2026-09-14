const SW_URL = '/sw.js';
const MANIFEST_URL = '/manifest.webmanifest';

export type SessionFloorCachePayload = {
    sessionId: string;
    cacheUrl: string;
    documentUrls: string[];
};

let registrationPromise: Promise<ServiceWorkerRegistration | null> | null = null;

export function isSessionFloorPath(pathname: string): boolean {
    return /\/sessions\/[^/]+\/floor\//.test(pathname);
}

export async function registerSessionFloorPwa(payload: SessionFloorCachePayload): Promise<void> {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    if (!isSessionFloorPath(window.location.pathname)) {
        return;
    }

    linkManifest();

    if (!registrationPromise) {
        registrationPromise = navigator.serviceWorker
            .register(SW_URL, { scope: '/' })
            .then((registration) => registration)
            .catch(() => null);
    }

    const registration = await registrationPromise;
    if (!registration) {
        return;
    }

    await navigator.serviceWorker.ready;

    registration.active?.postMessage({
        type: 'CACHE_SESSION_FLOOR',
        payload,
    });
}

function linkManifest(): void {
    if (document.querySelector(`link[rel="manifest"][href="${MANIFEST_URL}"]`)) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'manifest';
    link.href = MANIFEST_URL;
    document.head.appendChild(link);
}
