import jsQR from "jsqr";

export class QrReader {
    /**
     * Tries to find and decode a QR code in the provided ImageData
     */
    public static read(imageData: ImageData): any {
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
                return JSON.parse(code.data);
            } catch (e) {
                // Not valid JSON or string, return raw
                return code.data;
            }
        }

        return null;
    }
}
