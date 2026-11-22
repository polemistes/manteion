/** A picture made ready to send, and how to show it meanwhile. */
export type PreparedImage = {
    file: File;
    width: number;
    height: number;
    /** An object URL for the prepared picture; revoke it when done. */
    previewUrl: string;
};

/**
 * Prepares a picture the user picked for upload: decoded, scaled to fit
 * `maxEdge` on its longer side (never up), flattened on white and
 * re-encoded as a JPEG, more tightly if it would weigh more than
 * `maxBytes`. Drawing it through a canvas drops the camera's metadata
 * (place, device), and the browser applies the stored orientation while
 * drawing, so it comes out the right way up. Rejects when the file is not
 * a picture the browser can read.
 */
export async function prepareImageUpload(
    file: File,
    maxEdge: number,
    maxBytes = Infinity,
): Promise<PreparedImage> {
    const source = URL.createObjectURL(file);
    const image = new Image();

    image.src = source;

    try {
        await image.decode();

        const ratio = Math.min(
            1,
            maxEdge / Math.max(image.naturalWidth, image.naturalHeight),
        );
        const canvas = document.createElement('canvas');

        canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));

        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Could not draw the picture.');
        }

        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        let blob: Blob | null = null;

        // A busy photo can be heavy at the usual quality: step down until
        // it fits (the last step is taken whatever it weighs).
        for (const quality of [0.85, 0.72, 0.6, 0.45]) {
            blob = await new Promise<Blob | null>((resolve) =>
                canvas.toBlob(resolve, 'image/jpeg', quality),
            );

            if (!blob || blob.size <= maxBytes) {
                break;
            }
        }

        if (!blob) {
            throw new Error('Could not encode the picture.');
        }

        return {
            file: new File([blob], 'image.jpg', { type: 'image/jpeg' }),
            width: canvas.width,
            height: canvas.height,
            previewUrl: URL.createObjectURL(blob),
        };
    } finally {
        URL.revokeObjectURL(source);
    }
}

/**
 * The size a picture is shown at inside a square frame of `frame` pixels:
 * scaled down to fit, never up, and at least a few pixels each way. Falls
 * back to the frame's width when the size is unknown.
 */
export function fitInFrame(
    width: number | null,
    height: number | null,
    frame: number,
): { width: number; height: number } {
    if (!width || !height) {
        return { width: frame, height: frame };
    }

    const ratio = Math.min(1, frame / Math.max(width, height));

    return {
        width: Math.max(1, Math.round(width * ratio)),
        height: Math.max(1, Math.round(height * ratio)),
    };
}
