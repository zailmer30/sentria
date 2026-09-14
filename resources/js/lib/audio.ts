/**
 * Shared browser audio helpers for chamber capture and the listen-back test.
 *
 * Device names are matched loosely because the same interface is reported
 * differently by the browser, by PortAudio on the capture PC, and by Windows
 * itself ("Default - Scarlett 2i2 (USB)" vs "Scarlett 2i2 USB").
 */

export type LocalDevice = {
    id: string;
    name: string;
};

export type MediaErrorCode = 'denied' | 'insecure' | 'preview' | 'empty' | 'failed';

const NAME_PREFIXES = /^(default|communications) - /;
const NAME_SUFFIXES = / \((bluetooth|usb|analog|mme|windows directsound|windows wasapi)\)$/;

export function roughName(name: string): string {
    return name.toLowerCase().replace(NAME_PREFIXES, '').replace(NAME_SUFFIXES, '').trim();
}

export function deviceIdFor(devices: LocalDevice[], selectedName: string): string | undefined {
    if (selectedName === '') {
        return undefined;
    }

    const exact = devices.find((device) => device.name === selectedName && device.id !== '');

    if (exact) {
        return exact.id;
    }

    const wanted = roughName(selectedName);

    return devices.find((device) => device.id !== '' && roughName(device.name) === wanted)?.id;
}

export function toLocalDevices(listed: MediaDeviceInfo[], unnamed: string): LocalDevice[] {
    return listed
        .filter((device) => device.kind === 'audioinput')
        .map((device, index) => ({
            id: device.deviceId || `mic-${index}`,
            name: device.label.trim() || unnamed,
        }));
}

export function audioContextCtor(): typeof AudioContext | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    return window.AudioContext ?? (window as typeof window & { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
}

export function canCaptureAudio(): MediaErrorCode | null {
    if (typeof window !== 'undefined' && window.isSecureContext === false) {
        return 'insecure';
    }

    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia || !audioContextCtor()) {
        return 'preview';
    }

    return null;
}

export function mediaErrorCode(error: unknown): MediaErrorCode {
    const name = error instanceof DOMException ? error.name : '';

    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'AbortError') {
        return 'denied';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return 'empty';
    }

    if (name === 'SecurityError' || name === 'NotSupportedError') {
        return typeof window !== 'undefined' && window.isSecureContext === false ? 'insecure' : 'preview';
    }

    return 'failed';
}

/**
 * Processing is left off deliberately. Echo cancellation and automatic gain
 * are tuned for a headset on a call; on a hall mix they pump the floor noise
 * and chew the head of every utterance.
 */
export function audioConstraints(deviceId?: string, channelCount?: number): MediaTrackConstraints {
    return {
        ...(deviceId ? { deviceId: { ideal: deviceId } } : {}),
        ...(channelCount ? { channelCount: { ideal: channelCount } } : {}),
        echoCancellation: false,
        noiseSuppression: false,
        autoGainControl: false,
    };
}

export function mergeFloat32(chunks: Float32Array[]): Float32Array {
    const length = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    const samples = new Float32Array(length);
    let offset = 0;

    for (const chunk of chunks) {
        samples.set(chunk, offset);
        offset += chunk.length;
    }

    return samples;
}

export function encodeWav(samples: Float32Array, sampleRate: number): Blob {
    const dataSize = samples.length * 2;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);
    const writeString = (offset: number, value: string) => {
        for (let index = 0; index < value.length; index += 1) {
            view.setUint8(offset + index, value.charCodeAt(index));
        }
    };

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, dataSize, true);

    let offset = 44;

    for (let index = 0; index < samples.length; index += 1) {
        const sample = Math.max(-1, Math.min(1, samples[index] ?? 0));
        view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
        offset += 2;
    }

    return new Blob([buffer], { type: 'audio/wav' });
}
