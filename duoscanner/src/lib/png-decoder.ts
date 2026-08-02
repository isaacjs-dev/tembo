/**
 * Minimal PNG Decoder for React Native
 *
 * Replaces fast-png which depends on fflate (uses Web Workers — unavailable in RN).
 * Uses pako for DEFLATE decompression (pure JS, no Workers, no Node.js deps).
 *
 * Supports: 8-bit RGB, RGBA, Grayscale, Grayscale+Alpha
 * Does NOT support: 16-bit, indexed color (palette), interlaced PNGs
 * (ImageManipulator always outputs non-interlaced 8-bit RGB/RGBA, so this is fine)
 */

import pako from 'pako';

export interface DecodedPng {
  width: number;
  height: number;
  channels: 1 | 2 | 3 | 4;
  data: Uint8Array;
}

const PNG_SIGNATURE = [137, 80, 78, 71, 13, 10, 26, 10];

/**
 * Decode a PNG from raw bytes into pixel data.
 * @param buffer ArrayBuffer or Uint8Array of the PNG file
 * @returns DecodedPng with width, height, channels, and raw pixel data
 */
export function decodePng(buffer: ArrayBuffer | Uint8Array): DecodedPng {
  const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);

  // Verify PNG signature
  for (let i = 0; i < 8; i++) {
    if (bytes[i] !== PNG_SIGNATURE[i]) {
      throw new Error('Not a valid PNG file');
    }
  }

  // Parse chunks
  let offset = 8;
  let width = 0;
  let height = 0;
  let bitDepth = 0;
  let colorType = 0;
  const idatChunks: Uint8Array[] = [];

  while (offset < bytes.length) {
    const length = readUint32BE(bytes, offset);
    const type = readChunkType(bytes, offset + 4);
    const dataStart = offset + 8;

    if (type === 'IHDR') {
      width = readUint32BE(bytes, dataStart);
      height = readUint32BE(bytes, dataStart + 4);
      bitDepth = bytes[dataStart + 8];
      colorType = bytes[dataStart + 9];
      const compression = bytes[dataStart + 10];
      const filter = bytes[dataStart + 11];
      const interlace = bytes[dataStart + 12];

      if (bitDepth !== 8) {
        throw new Error(`Unsupported bit depth: ${bitDepth} (only 8-bit supported)`);
      }
      if (interlace !== 0) {
        throw new Error('Interlaced PNGs not supported');
      }
      if (compression !== 0 || filter !== 0) {
        throw new Error('Unsupported compression/filter method');
      }
    } else if (type === 'IDAT') {
      idatChunks.push(bytes.slice(dataStart, dataStart + length));
    } else if (type === 'IEND') {
      break;
    }

    // Move to next chunk: length(4) + type(4) + data(length) + crc(4)
    offset = dataStart + length + 4;
  }

  if (width === 0 || height === 0) {
    throw new Error('Missing IHDR chunk');
  }
  if (idatChunks.length === 0) {
    throw new Error('Missing IDAT data');
  }

  // Determine channels from color type
  let channels: 1 | 2 | 3 | 4;
  switch (colorType) {
    case 0: channels = 1; break; // Grayscale
    case 2: channels = 3; break; // RGB
    case 4: channels = 2; break; // Grayscale + Alpha
    case 6: channels = 4; break; // RGBA
    default:
      throw new Error(`Unsupported color type: ${colorType} (indexed/palette not supported)`);
  }

  // Concatenate all IDAT chunks
  const totalLen = idatChunks.reduce((s, c) => s + c.length, 0);
  const compressed = new Uint8Array(totalLen);
  let pos = 0;
  for (const chunk of idatChunks) {
    compressed.set(chunk, pos);
    pos += chunk.length;
  }

  // Decompress (zlib = 2-byte header + DEFLATE + 4-byte checksum)
  const decompressed = pako.inflate(compressed);

  // Unfilter scanlines
  const bytesPerPixel = channels;
  const stride = width * bytesPerPixel;
  const output = new Uint8Array(width * height * bytesPerPixel);

  let srcOff = 0;
  for (let y = 0; y < height; y++) {
    const filterByte = decompressed[srcOff++];
    const rowStart = y * stride;
    const prevRowStart = (y - 1) * stride;

    for (let x = 0; x < stride; x++) {
      const raw = decompressed[srcOff++];
      let val: number;

      switch (filterByte) {
        case 0: // None
          val = raw;
          break;
        case 1: // Sub
          val = raw + (x >= bytesPerPixel ? output[rowStart + x - bytesPerPixel] : 0);
          break;
        case 2: // Up
          val = raw + (y > 0 ? output[prevRowStart + x] : 0);
          break;
        case 3: // Average
          {
            const left = x >= bytesPerPixel ? output[rowStart + x - bytesPerPixel] : 0;
            const up = y > 0 ? output[prevRowStart + x] : 0;
            val = raw + Math.floor((left + up) / 2);
          }
          break;
        case 4: // Paeth
          {
            const a = x >= bytesPerPixel ? output[rowStart + x - bytesPerPixel] : 0;
            const b = y > 0 ? output[prevRowStart + x] : 0;
            const c = (x >= bytesPerPixel && y > 0) ? output[prevRowStart + x - bytesPerPixel] : 0;
            val = raw + paethPredictor(a, b, c);
          }
          break;
        default:
          val = raw; // Unknown filter, pass through
      }

      output[rowStart + x] = val & 0xFF;
    }
  }

  return { width, height, channels, data: output };
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function readUint32BE(data: Uint8Array, offset: number): number {
  return (
    (data[offset] << 24) |
    (data[offset + 1] << 16) |
    (data[offset + 2] << 8) |
    data[offset + 3]
  ) >>> 0;
}

function readChunkType(data: Uint8Array, offset: number): string {
  return String.fromCharCode(
    data[offset], data[offset + 1], data[offset + 2], data[offset + 3]
  );
}

function paethPredictor(a: number, b: number, c: number): number {
  const p = a + b - c;
  const pa = Math.abs(p - a);
  const pb = Math.abs(p - b);
  const pc = Math.abs(p - c);
  if (pa <= pb && pa <= pc) return a;
  if (pb <= pc) return b;
  return c;
}
