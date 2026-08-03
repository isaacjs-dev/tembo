import { describe, expect, it } from 'vitest';
import { DEFAULT_OPENCV_PATH, openCvUnavailableMessage, resolveOpenCvUrls } from '../opencv-loader';

describe('OpenCV worker runtime URLs', () => {
    it('uses an absolute configured asset URL and does not duplicate its local fallback', () => {
        expect(resolveOpenCvUrls(
            'https://tembo.aracruz.eu/vendor/opencv/opencv-4.8.0.js',
            ['/vendor/opencv/opencv-4.8.0.js'],
            'https://tembo.aracruz.eu/build/assets/worker.js',
        )).toEqual(['https://tembo.aracruz.eu/vendor/opencv/opencv-4.8.0.js']);
    });

    it('falls back to the versioned public runtime when no configured URL is supplied', () => {
        expect(resolveOpenCvUrls(undefined, [], 'https://tembo.aracruz.eu/build/assets/worker.js'))
            .toEqual([`https://tembo.aracruz.eu${DEFAULT_OPENCV_PATH}`]);
    });

    it('returns a controlled manual-review message when the runtime is unavailable', () => {
        expect(openCvUnavailableMessage()).toContain('conferência manual');
    });
});
