import { canUseHallSpeech, hallAgendaUtterance, speakHallAnnouncement, stopHallSpeech, unlockHallSpeech } from '@/lib/hallSpeech';
import { formatAgendaNumber, type AgendaItem } from '@/pages/Sessions/Floor/shared';
import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';

/**
 * Speaks the hall plate's current item when it changes, after the operator
 * has unlocked audio on this board. The item already on the floor when the
 * board opens, or when audio is turned on, is left unspoken.
 */
export function useHallAgendaSpeech(item: AgendaItem | null, locale: string) {
    const available = useSyncExternalStore(subscribeHallSpeech, canUseHallSpeech, hallSpeechUnavailable);
    const [enabled, setEnabled] = useState(false);
    const lastIdRef = useRef<string | null>(item?.id ?? null);

    useEffect(() => {
        const nextId = item?.id ?? null;
        const changed = nextId !== lastIdRef.current;
        lastIdRef.current = nextId;

        if (!enabled || !changed) {
            return;
        }

        if (!item) {
            stopHallSpeech();

            return;
        }

        const spoken = hallAgendaUtterance(formatAgendaNumber(item.item_number), item.title);

        if (spoken === '') {
            stopHallSpeech();

            return;
        }

        speakHallAnnouncement(spoken, locale);
    }, [item, enabled, locale]);

    useEffect(() => () => stopHallSpeech(), []);

    const toggle = useCallback(() => {
        if (!available) {
            return;
        }

        if (enabled) {
            stopHallSpeech();
            setEnabled(false);

            return;
        }

        unlockHallSpeech();
        setEnabled(true);
    }, [available, enabled]);

    return { available, enabled, toggle };
}

function subscribeHallSpeech(): () => void {
    return () => undefined;
}

function hallSpeechUnavailable(): boolean {
    return false;
}
