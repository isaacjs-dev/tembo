import * as ImageManipulator from 'expo-image-manipulator';
import * as FileSystem from 'expo-file-system';

const SCANS_DIR = `${(FileSystem as any).documentDirectory}scans/`;

export async function ensureScansDirectory(): Promise<void> {
  const info = await FileSystem.getInfoAsync(SCANS_DIR);
  if (!info.exists) {
    await FileSystem.makeDirectoryAsync(SCANS_DIR, { intermediates: true });
  }
}

export async function saveScanImage(uri: string, localId: string): Promise<string> {
  await ensureScansDirectory();
  const destUri = `${SCANS_DIR}${localId}.jpg`;
  await FileSystem.copyAsync({ from: uri, to: destUri });
  return destUri;
}

export async function cropAndRotateImage(
  uri: string,
  crop: { originX: number; originY: number; width: number; height: number },
  rotation: number
): Promise<string> {
  const actions: ImageManipulator.Action[] = [];

  if (rotation !== 0) {
    actions.push({ rotate: rotation });
  }

  if (crop.width > 0 && crop.height > 0) {
    actions.push({ crop });
  }

  const result = await ImageManipulator.manipulateAsync(uri, actions, {
    compress: 0.85,
    format: ImageManipulator.SaveFormat.JPEG,
  });

  return result.uri;
}

export async function resizeImage(uri: string, maxWidth: number): Promise<string> {
  const result = await ImageManipulator.manipulateAsync(
    uri,
    [{ resize: { width: maxWidth } }],
    { compress: 0.85, format: ImageManipulator.SaveFormat.JPEG }
  );
  return result.uri;
}

export async function deleteScanImage(localId: string): Promise<void> {
  const uri = `${SCANS_DIR}${localId}.jpg`;
  const info = await FileSystem.getInfoAsync(uri);
  if (info.exists) {
    await FileSystem.deleteAsync(uri);
  }
}

export function generateLocalId(): string {
  return `scan_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`;
}
