/**
 * Chamber voice-activity detection, running on the audio thread.
 *
 * This is a port of the detector in `services/chamber-capture/capture.py`, and
 * the two must stay in step: they cut chunks on the same boundaries and hand
 * them to the same upload endpoint, so a sitting recorded in the browser and a
 * sitting recorded by the daemon produce the same transcript shape.
 *
 * Served straight out of `public/` rather than through Vite: an AudioWorklet
 * module is evaluated in its own global scope with no import support, so it
 * must reach the browser exactly as written. `sampleRate` is a global provided
 * by AudioWorkletGlobalScope.
 *
 * Posts to the main thread:
 *   { type: 'level',     rms }                          peak RMS over the window
 *   { type: 'utterance', pcm, startedAtMs, endedAtMs }   speech, PCM transferred
 */

const BLOCK_MS = 30;
const LEVEL_EVERY_BLOCKS = 3;

class ChamberVadProcessor extends AudioWorkletProcessor {
    constructor(options) {
        super();

        const tuning = (options && options.processorOptions) || {};

        this.vadRms = tuning.vadRms ?? 0.012;
        this.minSpeechMs = tuning.minSpeechMs ?? 400;
        this.silenceEndMs = tuning.silenceEndMs ?? 600;
        this.maxUtteranceMs = tuning.maxUtteranceMs ?? 25000;

        this.blockSize = Math.max(1, Math.round((sampleRate * BLOCK_MS) / 1000));
        this.blockMs = (this.blockSize / sampleRate) * 1000;

        // Partial 30ms block carried across render quanta (128 frames each).
        this.pending = new Float32Array(this.blockSize);
        this.pendingLength = 0;

        this.elapsedMs = 0;
        this.levelBlocks = 0;
        this.levelPeak = 0;

        this.speaking = false;
        this.startedAtMs = 0;
        this.speechMs = 0;
        this.silenceMs = 0;
        this.frames = [];

        this.running = true;
        this.port.onmessage = (event) => {
            if (event.data && event.data.type === 'stop') {
                this.flush(true);
                this.running = false;
            }
        };
    }

    /** Mirrors `mixdown()` in capture.py: fold every input channel to mono. */
    static mono(input, target) {
        const first = input[0];

        if (input.length === 1) {
            target.set(first);
            return;
        }

        for (let i = 0; i < first.length; i += 1) {
            let sum = 0;

            for (let channel = 0; channel < input.length; channel += 1) {
                sum += input[channel][i];
            }

            target[i] = sum / input.length;
        }
    }

    flush(final) {
        const speechMs = this.speechMs;
        const frames = this.frames;

        this.frames = [];
        this.speaking = false;
        this.speechMs = 0;
        this.silenceMs = 0;

        if (frames.length === 0 || speechMs < this.minSpeechMs) {
            return;
        }

        let length = 0;

        for (const frame of frames) {
            length += frame.length;
        }

        const pcm = new Float32Array(length);
        let offset = 0;

        for (const frame of frames) {
            pcm.set(frame, offset);
            offset += frame.length;
        }

        this.port.postMessage(
            {
                type: 'utterance',
                pcm,
                startedAtMs: Math.round(this.startedAtMs),
                endedAtMs: Math.round(this.elapsedMs),
                final: final === true,
            },
            [pcm.buffer],
        );
    }

    /** One complete 30ms block: the unit the Python detector also works in. */
    consumeBlock(block) {
        let sum = 0;

        for (let i = 0; i < block.length; i += 1) {
            sum += block[i] * block[i];
        }

        const energy = Math.sqrt(sum / block.length);

        this.elapsedMs += this.blockMs;
        this.levelPeak = Math.max(this.levelPeak, energy);
        this.levelBlocks += 1;

        if (this.levelBlocks >= LEVEL_EVERY_BLOCKS) {
            this.port.postMessage({ type: 'level', rms: this.levelPeak });
            this.levelBlocks = 0;
            this.levelPeak = 0;
        }

        if (energy >= this.vadRms) {
            if (!this.speaking) {
                this.speaking = true;
                this.startedAtMs = this.elapsedMs - this.blockMs;
                this.frames = [];
                this.speechMs = 0;
                this.silenceMs = 0;
            }

            this.frames.push(block);
            this.speechMs += this.blockMs;
            this.silenceMs = 0;

            if (this.speechMs >= this.maxUtteranceMs) {
                this.flush(false);
            }

            return;
        }

        if (!this.speaking) {
            return;
        }

        // Trailing silence rides along so the tail of the word is not clipped.
        this.frames.push(block);
        this.silenceMs += this.blockMs;

        if (this.silenceMs >= this.silenceEndMs) {
            this.flush(false);
        }
    }

    /**
     * The single output is left silent on purpose. It exists only so the node
     * has a path to the destination, which is what keeps Chrome rendering it.
     */
    process(inputs) {
        if (!this.running) {
            return false;
        }

        const input = inputs[0];

        if (!input || input.length === 0 || !input[0]) {
            return true;
        }

        const quantum = new Float32Array(input[0].length);
        ChamberVadProcessor.mono(input, quantum);

        let read = 0;

        while (read < quantum.length) {
            const take = Math.min(this.blockSize - this.pendingLength, quantum.length - read);
            this.pending.set(quantum.subarray(read, read + take), this.pendingLength);
            this.pendingLength += take;
            read += take;

            if (this.pendingLength === this.blockSize) {
                this.consumeBlock(this.pending);
                this.pending = new Float32Array(this.blockSize);
                this.pendingLength = 0;
            }
        }

        return true;
    }
}

registerProcessor('chamber-vad', ChamberVadProcessor);
