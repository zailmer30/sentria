export type DiffToken = {
    value: string;
    type: 'equal' | 'added' | 'removed';
};

function tokenize(text: string): string[] {
    return text.split(/(\s+)/).filter((part) => part !== '');
}

/**
 * Word-level diff for original STT vs secretariat wording. Segments are short
 * enough that a straightforward LCS is enough — no extra package.
 */
export function diffWords(original: string, current: string): DiffToken[] {
    const left = tokenize(original);
    const right = tokenize(current);
    const rows = left.length;
    const cols = right.length;
    const table: number[][] = Array.from({ length: rows + 1 }, () => Array.from({ length: cols + 1 }, () => 0));

    for (let i = 1; i <= rows; i++) {
        for (let j = 1; j <= cols; j++) {
            table[i][j] =
                left[i - 1] === right[j - 1]
                    ? (table[i - 1]?.[j - 1] ?? 0) + 1
                    : Math.max(table[i - 1]?.[j] ?? 0, table[i]?.[j - 1] ?? 0);
        }
    }

    const tokens: DiffToken[] = [];
    let i = rows;
    let j = cols;

    while (i > 0 && j > 0) {
        if (left[i - 1] === right[j - 1]) {
            tokens.push({ value: left[i - 1] ?? '', type: 'equal' });
            i -= 1;
            j -= 1;
        } else if ((table[i - 1]?.[j] ?? 0) >= (table[i]?.[j - 1] ?? 0)) {
            tokens.push({ value: left[i - 1] ?? '', type: 'removed' });
            i -= 1;
        } else {
            tokens.push({ value: right[j - 1] ?? '', type: 'added' });
            j -= 1;
        }
    }

    while (i > 0) {
        tokens.push({ value: left[i - 1] ?? '', type: 'removed' });
        i -= 1;
    }

    while (j > 0) {
        tokens.push({ value: right[j - 1] ?? '', type: 'added' });
        j -= 1;
    }

    return tokens.reverse();
}
