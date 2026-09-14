import { chamberCapture, type CaptureSnapshot, type CaptureState, type CaptureTuning } from '@/lib/chamberCapture';
import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';

export type {
    CaptureError,
    CaptureMode,
    CapturePhase,
    CaptureSnapshot,
    CaptureState,
    CaptureStats,
    CaptureTuning,
} from '@/lib/chamberCapture';

/**
 * Read-only view of the capture engine, for surfaces that only need to know
 * whether a sitting is being recorded — the floor chrome, for instance.
 */
export function useCaptureStatus(): CaptureSnapshot {
    return useSyncExternalStore(chamberCapture.subscribe, chamberCapture.getSnapshot, chamberCapture.getServerSnapshot);
}

/**
 * The level meter, polled on a frame rather than pushed through the store.
 * Keeping it separate means a moving bar does not re-render the console.
 */
export function useCaptureLevel(active: boolean): number {
    const [level, setLevel] = useState(0);

    useEffect(() => {
        if (!active) {
            return;
        }

        let frame = 0;

        const tick = () => {
            setLevel((current) => {
                const next = chamberCapture.getLevel();

                return Math.abs(next - current) < 0.0005 ? current : next;
            });
            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [active]);

    return active ? level : 0;
}

export function useChamberCapture({
    sessionId,
    sessionLabel,
    tuning,
    initialState,
    preferredDeviceName,
}: {
    sessionId: string;
    sessionLabel: string;
    tuning: CaptureTuning;
    initialState: CaptureState;
    preferredDeviceName: string;
}) {
    const snapshot = useCaptureStatus();

    // Inertia hands us a fresh props object on every render, so the seed value
    // is read once rather than becoming an effect dependency that re-attaches.
    const seed = useRef(initialState);

    useEffect(() => chamberCapture.attach(sessionId, sessionLabel, seed.current), [sessionId, sessionLabel]);

    const start = useCallback(
        (deviceId?: string) => chamberCapture.start({ sessionId, sessionLabel, tuning, preferredDeviceName, deviceId }),
        [preferredDeviceName, sessionId, sessionLabel, tuning],
    );

    const refreshDevices = useCallback((unnamed: string) => chamberCapture.refreshDevices(unnamed), []);

    return {
        ...snapshot,
        /** True when the engine is recording a different sitting than this page. */
        foreign: snapshot.sessionId !== null && snapshot.sessionId !== sessionId,
        start,
        stop: () => chamberCapture.stop(),
        refreshDevices,
    };
}
