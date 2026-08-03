/**
 * Utilities kept separate from the worker so they can be unit-tested without
 * loading the large OpenCV runtime.
 */
export const DEFAULT_OPENCV_PATH = '/vendor/opencv/opencv-4.8.0.js';

/**
 * A worker resolves relative imports against its generated Vite chunk. Turn
 * configured paths into stable, absolute URLs for the Laravel public asset.
 */
export function resolveOpenCvUrls(
    primaryUrl?: string,
    fallbackUrls: string[] = [],
    baseUrl: string = 'https://tembo.invalid/',
): string[] {
    const candidates = [primaryUrl, ...fallbackUrls, DEFAULT_OPENCV_PATH];
    const urls: string[] = [];

    for (const candidate of candidates) {
        if (!candidate || !candidate.trim()) continue;

        try {
            const url = new URL(candidate, baseUrl).href;
            if (!urls.includes(url)) urls.push(url);
        } catch {
            // Ignore an invalid optional URL and keep the known local runtime.
        }
    }

    return urls;
}

export function openCvUnavailableMessage(): string {
    return 'O componente de leitura automática não pôde ser carregado. Verifique a conexão e tente novamente; a foto ainda pode ser enviada para conferência manual.';
}
