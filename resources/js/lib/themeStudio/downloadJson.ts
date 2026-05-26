/**
 * Tiny DOM-I/O helpers for the Theme Studio import/export buttons. Kept out
 * of the page component so the JSX stays declarative and the logic is easy
 * to stub in tests.
 */

/** Triggers a browser download of `text` as `filename`. */
export function triggerDownload(filename: string, text: string): void {
    const blob = new Blob([text], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    // Revoke on the next tick so the click has a chance to start the download.
    setTimeout(() => URL.revokeObjectURL(url), 0);
}

/**
 * Opens a file picker and resolves with the chosen file's text, or null if
 * the user cancelled. Accepts only .json.
 */
export function pickJsonFile(): Promise<string | null> {
    return new Promise((resolve) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json,application/json';
        input.onchange = () => {
            const file = input.files?.[0];
            if (!file) {
                resolve(null);
                return;
            }
            file.text().then(resolve).catch(() => resolve(null));
        };
        // Some browsers need the input in the DOM for `.click()` to work.
        input.style.display = 'none';
        document.body.appendChild(input);
        input.click();
        input.remove();
    });
}
