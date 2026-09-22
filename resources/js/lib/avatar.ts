// Deterministic placeholder color + initials for user avatars when no photo
// exists.
const avatarPalette = [
    '#2563eb',
    '#059669',
    '#dc2626',
    '#7c3aed',
    '#d97706',
    '#0891b2',
    '#db2777',
    '#4b5563',
];

export function avatarColor(id: number | null | undefined): string {
    if (!id) {
        return '#6b7280';
    }

    return avatarPalette[id % avatarPalette.length];
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((s) => s[0]?.toUpperCase() ?? '')
        .join('');
}
