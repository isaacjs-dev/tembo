export type QuarterTurns = 0 | 1 | 2 | 3;

interface Point { x: number; y: number }
interface Bounds { origin: Point; size: { width: number; height: number } }

/**
 * Current answer-sheet contracts place the QR in the physical top-right.
 * Its observed quadrant therefore disambiguates otherwise symmetric markers.
 */
export function orientationFromQrLocation(
  cornerPoints: Point[] | undefined,
  bounds: Bounds | undefined,
  frameWidth: number,
  frameHeight: number,
): QuarterTurns | null {
  let center: Point | null = null;
  if (cornerPoints && cornerPoints.length >= 3) {
    center = {
      x: cornerPoints.reduce((sum, point) => sum + point.x, 0) / cornerPoints.length,
      y: cornerPoints.reduce((sum, point) => sum + point.y, 0) / cornerPoints.length,
    };
  } else if (bounds && Number(bounds.size.width) > 0 && Number(bounds.size.height) > 0) {
    center = {
      x: bounds.origin.x + Number(bounds.size.width) / 2,
      y: bounds.origin.y + Number(bounds.size.height) / 2,
    };
  }
  if (!center || frameWidth <= 0 || frameHeight <= 0) return null;
  const right = center.x > frameWidth * 0.55;
  const left = center.x < frameWidth * 0.45;
  const bottom = center.y > frameHeight * 0.55;
  const top = center.y < frameHeight * 0.45;
  if (right && top) return 0;
  if (left && top) return 1;
  if (left && bottom) return 2;
  if (right && bottom) return 3;
  return null;
}

export function rotateRgbaQuarterTurns(
  pixels: { data: Uint8Array | Uint8ClampedArray; width: number; height: number },
  turns: QuarterTurns,
): { data: Uint8ClampedArray; width: number; height: number } {
  if (turns === 0) return {
    data: new Uint8ClampedArray(pixels.data),
    width: pixels.width,
    height: pixels.height,
  };
  const width = turns % 2 === 0 ? pixels.width : pixels.height;
  const height = turns % 2 === 0 ? pixels.height : pixels.width;
  const output = new Uint8ClampedArray(width * height * 4);
  for (let y = 0; y < pixels.height; y += 1) {
    for (let x = 0; x < pixels.width; x += 1) {
      let destinationX: number;
      let destinationY: number;
      if (turns === 1) {
        destinationX = pixels.height - 1 - y;
        destinationY = x;
      } else if (turns === 2) {
        destinationX = pixels.width - 1 - x;
        destinationY = pixels.height - 1 - y;
      } else {
        destinationX = y;
        destinationY = pixels.width - 1 - x;
      }
      const sourceOffset = (y * pixels.width + x) * 4;
      const destinationOffset = (destinationY * width + destinationX) * 4;
      output.set(pixels.data.slice(sourceOffset, sourceOffset + 4), destinationOffset);
    }
  }
  return { data: output, width, height };
}

/** Maps overlay coordinates from the normalized image back to the captured photo. */
export function pointFromNormalizedImage(
  point: Point,
  originalWidth: number,
  originalHeight: number,
  turns: QuarterTurns,
): Point {
  if (turns === 1) return { x: point.y, y: originalHeight - 1 - point.x };
  if (turns === 2) return { x: originalWidth - 1 - point.x, y: originalHeight - 1 - point.y };
  if (turns === 3) return { x: originalWidth - 1 - point.y, y: point.x };
  return { ...point };
}
