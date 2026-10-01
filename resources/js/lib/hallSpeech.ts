/**
 * Chamber announcements via the browser's built-in speech synthesis.
 * No network, no model: OS voices only. The hall board uses this so the
 * PA can speak the item that just arrived on the floor.
 */

const FILIPINO_LANGS = ['fil', 'tl'];
const ENGLISH_LANGS = ['en'];

export function canUseHallSpeech(): boolean {
    return typeof window !== 'undefined' && typeof window.speechSynthesis?.speak === 'function';
}

export function bcp47ForLocale(locale: string): string {
    return locale === 'fil' ? 'fil-PH' : 'en-PH';
}

export function hallAgendaUtterance(number: string, title: string): string {
    return [number.trim(), title.trim()].filter(Boolean).join(' ');
}

export function pickHallVoice(voices: SpeechSynthesisVoice[], locale: string): SpeechSynthesisVoice | undefined {
    const preferred = locale === 'fil' ? FILIPINO_LANGS : ENGLISH_LANGS;
    const fallback = locale === 'fil' ? ENGLISH_LANGS : [];

    return matchVoice(voices, preferred) ?? matchVoice(voices, fallback);
}

/**
 * Prime the engine inside a click. Later agenda changes are not a user
 * gesture; Safari in particular will swallow those speaks unless this
 * page has already spoken once.
 */
export function unlockHallSpeech(): void {
    const synth = synthesis();

    if (!synth) {
        return;
    }

    synth.getVoices();

    const prime = new SpeechSynthesisUtterance('\u200b');
    prime.volume = 0;
    prime.rate = 2;
    prime.lang = 'en';
    synth.speak(prime);
}

export function stopHallSpeech(): void {
    const synth = synthesis();

    if (!synth) {
        return;
    }

    clearQueuedSpeak();
    synth.cancel();
}

export function speakHallAnnouncement(text: string, locale: string): void {
    const synth = synthesis();
    const spoken = text.trim();

    if (!synth || spoken === '') {
        return;
    }

    stopHallSpeech();

    const utterance = new SpeechSynthesisUtterance(spoken);
    attachVoice(utterance, locale, synth.getVoices());

    queuedSpeak = window.setTimeout(() => {
        queuedSpeak = null;
        attachVoice(utterance, locale, synth.getVoices());

        if (synth.paused) {
            synth.resume();
        }

        synth.speak(utterance);
    }, 50);
}

let queuedSpeak: number | null = null;

function synthesis(): SpeechSynthesis | null {
    return canUseHallSpeech() ? window.speechSynthesis : null;
}

function clearQueuedSpeak(): void {
    if (queuedSpeak === null) {
        return;
    }

    window.clearTimeout(queuedSpeak);
    queuedSpeak = null;
}

function attachVoice(utterance: SpeechSynthesisUtterance, locale: string, voices: SpeechSynthesisVoice[]): void {
    const voice = pickHallVoice(voices, locale);

    if (voice) {
        utterance.voice = voice;
        utterance.lang = voice.lang;

        return;
    }

    utterance.lang = bcp47ForLocale(locale);
}

function matchVoice(voices: SpeechSynthesisVoice[], prefixes: string[]): SpeechSynthesisVoice | undefined {
    let best: SpeechSynthesisVoice | undefined;
    let bestScore = -1;

    for (const voice of voices) {
        const lang = voice.lang.toLowerCase();
        const index = prefixes.findIndex((prefix) => lang === prefix || lang.startsWith(`${prefix}-`));

        if (index < 0) {
            continue;
        }

        const score = (voice.localService ? 1000 : 0) + (100 - index);

        if (score > bestScore) {
            best = voice;
            bestScore = score;
        }
    }

    return best;
}
