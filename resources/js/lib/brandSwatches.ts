/** Mirrors `App\Enums\PlatePattern`; each id has a `[data-plate-pattern]` rule in app.css. */
export const PLATE_PATTERNS = ['authored', 'none', 'lines', 'grid', 'dots', 'diagonal', 'glow'] as const;

export type PlatePattern = (typeof PLATE_PATTERNS)[number];

export type BrandSwatch = {
    id: string;
    hex: string;
};

/** Mid-tones for actions: every one keeps white button text at 4.5:1 or better. */
export const ACCENT_SWATCHES: BrandSwatch[] = [
    { id: 'ph_blue', hex: '#0038A8' },
    { id: 'royal_blue', hex: '#1D4ED8' },
    { id: 'azure', hex: '#0369A1' },
    { id: 'teal', hex: '#0F766E' },
    { id: 'emerald', hex: '#047857' },
    { id: 'forest', hex: '#166534' },
    { id: 'crimson', hex: '#B0103A' },
    { id: 'maroon', hex: '#881337' },
    { id: 'magenta', hex: '#BE185D' },
    { id: 'purple', hex: '#6D28D9' },
    { id: 'orange', hex: '#C2410C' },
    { id: 'gold', hex: '#A16207' },
    { id: 'brown', hex: '#8B4513' },
    { id: 'slate', hex: '#334155' },
    { id: 'charcoal', hex: '#1F2937' },
];

/** Deep tones for plates: white text on each clears the server's 4.5:1 floor. */
export const PLATE_SWATCHES: BrandSwatch[] = [
    { id: 'navy', hex: '#132042' },
    { id: 'midnight', hex: '#0F172A' },
    { id: 'royal_blue', hex: '#1E3A8A' },
    { id: 'azure', hex: '#0C4A6E' },
    { id: 'teal', hex: '#134E4A' },
    { id: 'forest', hex: '#14532D' },
    { id: 'emerald', hex: '#064E3B' },
    { id: 'crimson', hex: '#7A0F24' },
    { id: 'maroon', hex: '#4C0519' },
    { id: 'purple', hex: '#3B0764' },
    { id: 'plum', hex: '#4A1942' },
    { id: 'gold', hex: '#5C3D07' },
    { id: 'brown', hex: '#3F2314' },
    { id: 'slate', hex: '#1E293B' },
    { id: 'charcoal', hex: '#1F2328' },
];
