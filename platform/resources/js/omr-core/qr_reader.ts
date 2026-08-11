import jsQR from "jsqr";

export class QrReader {
    /**
     * Tries to find and decode a QR code in the provided ImageData
     */
    public static read(imageData: ImageData): { payload: unknown, orientationQuarterTurns: 0 | 1 | 2 | 3 } | null {
        // Try reading directly
        let code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: "dontInvert",
        });

        // If it fails, try inverting colors (useful for some lighting conditions)
        if (!code) {
            code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "invertFirst",
            });
        }

        if (code && code.data) {
            try {
                return {
                    payload: JSON.parse(code.data),
                    orientationQuarterTurns: this.orientationFromLocation(code.location, imageData.width, imageData.height),
                };
            } catch (e) {
                return null;
            }
        }

        return null;
    }

    public static rotate(imageData: ImageData, turns: 0 | 1 | 2 | 3): ImageData {
        if (turns === 0) return imageData;
        const width = turns % 2 === 0 ? imageData.width : imageData.height;
        const height = turns % 2 === 0 ? imageData.height : imageData.width;
        const output = new Uint8ClampedArray(width * height * 4);
        for (let y = 0; y < imageData.height; y++) {
            for (let x = 0; x < imageData.width; x++) {
                let destinationX: number;
                let destinationY: number;
                if (turns === 1) {
                    destinationX = imageData.height - 1 - y;
                    destinationY = x;
                } else if (turns === 2) {
                    destinationX = imageData.width - 1 - x;
                    destinationY = imageData.height - 1 - y;
                } else {
                    destinationX = y;
                    destinationY = imageData.width - 1 - x;
                }
                const sourceOffset = (y * imageData.width + x) * 4;
                const destinationOffset = (destinationY * width + destinationX) * 4;
                output.set(imageData.data.slice(sourceOffset, sourceOffset + 4), destinationOffset);
            }
        }
        return new ImageData(output, width, height);
    }

    private static orientationFromLocation(location: any, width: number, height: number): 0 | 1 | 2 | 3 {
        const points = [location?.topLeftCorner, location?.topRightCorner, location?.bottomRightCorner, location?.bottomLeftCorner]
            .filter((point) => point && Number.isFinite(point.x) && Number.isFinite(point.y));
        if (points.length !== 4) throw new Error('QR orientation unavailable');
        const center = {
            x: points.reduce((sum, point) => sum + point.x, 0) / points.length,
            y: points.reduce((sum, point) => sum + point.y, 0) / points.length,
        };
        const right = center.x > width * 0.55;
        const left = center.x < width * 0.45;
        const top = center.y < height * 0.45;
        const bottom = center.y > height * 0.55;
        if (right && top) return 0;
        if (left && top) return 1;
        if (left && bottom) return 2;
        if (right && bottom) return 3;
        throw new Error('QR outside canonical corner zones');
    }
}
