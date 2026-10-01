function lightnessAndChroma(r: number, g: number, b: number): { lightness: number; chroma: number } {
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);

    return {
        lightness: (max + min) / 510,
        chroma: max - min,
    };
}

function isPaper(r: number, g: number, b: number, a: number): boolean {
    if (a < 128) {
        return false;
    }

    const { lightness, chroma } = lightnessAndChroma(r, g, b);

    return lightness >= 0.58 && chroma <= 92;
}

function pixelIndex(x: number, y: number, width: number): number {
    return (y * width + x) * 4;
}

export async function knockoutPaper(file: File): Promise<string> {
    const objectUrl = URL.createObjectURL(file);

    try {
        const image = await loadImage(objectUrl);
        const canvas = document.createElement('canvas');
        canvas.width = image.width;
        canvas.height = image.height;
        const context = canvas.getContext('2d');

        if (!context) {
            return objectUrl;
        }

        context.drawImage(image, 0, 0);
        const frame = context.getImageData(0, 0, canvas.width, canvas.height);
        floodPaper(frame);
        context.putImageData(frame, 0, 0);

        const processed = await new Promise<string>((resolve) => {
            canvas.toBlob((blob) => {
                resolve(blob ? URL.createObjectURL(blob) : objectUrl);
            }, 'image/png');
        });

        if (processed !== objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }

        return processed;
    } catch {
        return objectUrl;
    }
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Could not read that image.'));
        image.src = src;
    });
}

function floodPaper(frame: ImageData): void {
    const { data, width, height } = frame;
    const seen = new Uint8Array(width * height);
    const queue: number[] = [];
    let head = 0;

    const enqueue = (x: number, y: number) => {
        const i = y * width + x;

        if (seen[i]) {
            return;
        }

        const p = pixelIndex(x, y, width);

        if (!isPaper(data[p], data[p + 1], data[p + 2], data[p + 3])) {
            return;
        }

        seen[i] = 1;
        queue.push(i);
    };

    for (let x = 0; x < width; x++) {
        enqueue(x, 0);
        enqueue(x, height - 1);
    }

    for (let y = 0; y < height; y++) {
        enqueue(0, y);
        enqueue(width - 1, y);
    }

    while (head < queue.length) {
        const i = queue[head] ?? 0;
        head += 1;
        const x = i % width;
        const y = Math.floor(i / width);
        const p = pixelIndex(x, y, width);
        data[p] = 0;
        data[p + 1] = 0;
        data[p + 2] = 0;
        data[p + 3] = 0;

        const neighbors = [i - 1, i + 1, i - width, i + width];

        for (const next of neighbors) {
            if (next < 0 || next >= width * height || seen[next]) {
                continue;
            }

            const nx = next % width;
            const ny = Math.floor(next / width);

            if (Math.abs(nx - x) + Math.abs(ny - y) !== 1) {
                continue;
            }

            const np = pixelIndex(nx, ny, width);

            if (!isPaper(data[np], data[np + 1], data[np + 2], data[np + 3])) {
                continue;
            }

            seen[next] = 1;
            queue.push(next);
        }
    }
}
