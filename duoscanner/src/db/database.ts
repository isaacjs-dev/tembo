/**
 * Local SQLite database for Duoscanner.
 *
 * Replaces SecureStore (2KB limit per item) with expo-sqlite
 * for reliable offline persistence of scans, exams, sync queue, and config.
 */
import * as SQLite from 'expo-sqlite';

let db: SQLite.SQLiteDatabase | null = null;

export async function getDatabase(): Promise<SQLite.SQLiteDatabase> {
  if (!db) {
    db = await SQLite.openDatabaseAsync('duoscanner.db');
    await runMigrations(db);
  }
  return db;
}

async function runMigrations(database: SQLite.SQLiteDatabase): Promise<void> {
  await database.execAsync(`
    PRAGMA journal_mode = WAL;
    PRAGMA foreign_keys = ON;

    CREATE TABLE IF NOT EXISTS local_exams (
      exam_id INTEGER PRIMARY KEY,
      data_json TEXT NOT NULL,
      server_version INTEGER NOT NULL DEFAULT 1,
      downloaded_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS local_scans (
      local_id TEXT PRIMARY KEY,
      exam_id INTEGER NOT NULL,
      copy_id INTEGER,
      student_id INTEGER,
      status TEXT NOT NULL DEFAULT 'processing',
      image_uri TEXT NOT NULL,
      detected_answers TEXT,
      confirmed_answers TEXT,
      confidence_score REAL,
      answer_sheet_type TEXT NOT NULL DEFAULT 'essential',
      scan_mode TEXT NOT NULL DEFAULT 'hybrid',
      idempotency_key TEXT UNIQUE NOT NULL,
      session_id TEXT,
      page_index INTEGER DEFAULT 1,
      total_pages INTEGER DEFAULT 1,
      created_at TEXT NOT NULL,
      synced_at TEXT,
      server_scan_id INTEGER,
      payload_json TEXT
    );

    CREATE TABLE IF NOT EXISTS sync_queue (
      local_id TEXT PRIMARY KEY,
      retry_count INTEGER DEFAULT 0,
      next_retry_at TEXT,
      last_error TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS dead_letter (
      local_id TEXT PRIMARY KEY,
      retry_count INTEGER DEFAULT 0,
      last_error TEXT,
      moved_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS local_config (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL,
      config_version INTEGER NOT NULL DEFAULT 0,
      cached_at TEXT NOT NULL
    );
  `);

  // Upgrade databases created by earlier builds without deleting offline data.
  const scanColumns = await database.getAllAsync<{ name: string }>('PRAGMA table_info(local_scans)');
  if (!scanColumns.some((column) => column.name === 'payload_json')) {
    await database.execAsync('ALTER TABLE local_scans ADD COLUMN payload_json TEXT;');
  }
}

/* ── Config Operations ── */

export async function getConfig(key: string): Promise<{ value: string; configVersion: number } | null> {
  const database = await getDatabase();
  const row = await database.getFirstAsync<{ value: string; config_version: number }>(
    'SELECT value, config_version FROM local_config WHERE key = ?',
    [key]
  );
  return row ? { value: row.value, configVersion: row.config_version } : null;
}

export async function setConfig(key: string, value: string, configVersion: number): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    `INSERT OR REPLACE INTO local_config (key, value, config_version, cached_at)
     VALUES (?, ?, ?, ?)`,
    [key, value, configVersion, new Date().toISOString()]
  );
}

/* ── Exam Operations ── */

export async function getLocalExam(examId: number): Promise<{ dataJson: string; serverVersion: number } | null> {
  const database = await getDatabase();
  const row = await database.getFirstAsync<{ data_json: string; server_version: number }>(
    'SELECT data_json, server_version FROM local_exams WHERE exam_id = ?',
    [examId]
  );
  return row ? { dataJson: row.data_json, serverVersion: row.server_version } : null;
}

export async function saveLocalExam(examId: number, dataJson: string, serverVersion: number): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    `INSERT OR REPLACE INTO local_exams (exam_id, data_json, server_version, downloaded_at)
     VALUES (?, ?, ?, ?)`,
    [examId, dataJson, serverVersion, new Date().toISOString()]
  );
}

export async function getAllLocalExams(): Promise<
  Array<{ exam_id: number; data_json: string; server_version: number; downloaded_at: string }>
> {
  const database = await getDatabase();
  return database.getAllAsync(
    'SELECT exam_id, data_json, server_version, downloaded_at FROM local_exams ORDER BY downloaded_at DESC'
  );
}

export async function deleteLocalExam(examId: number): Promise<void> {
  const database = await getDatabase();
  await database.runAsync('DELETE FROM local_exams WHERE exam_id = ?', [examId]);
}

export async function getExamVersion(examId: number): Promise<number | null> {
  const database = await getDatabase();
  const row = await database.getFirstAsync<{ server_version: number }>(
    'SELECT server_version FROM local_exams WHERE exam_id = ?',
    [examId]
  );
  return row?.server_version ?? null;
}

/* ── Scan Operations ── */

export interface LocalScanRow {
  local_id: string;
  exam_id: number;
  copy_id: number | null;
  student_id: number | null;
  status: string;
  image_uri: string;
  detected_answers: string | null;
  confirmed_answers: string | null;
  confidence_score: number | null;
  answer_sheet_type: string;
  scan_mode: string;
  idempotency_key: string;
  session_id: string | null;
  page_index: number;
  total_pages: number;
  created_at: string;
  synced_at: string | null;
  server_scan_id: number | null;
  payload_json: string | null;
}

export async function saveScan(scan: LocalScanRow): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    `INSERT OR REPLACE INTO local_scans
     (local_id, exam_id, copy_id, student_id, status, image_uri, detected_answers,
      confirmed_answers, confidence_score, answer_sheet_type, scan_mode, idempotency_key,
       session_id, page_index, total_pages, created_at, synced_at, server_scan_id, payload_json)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      scan.local_id, scan.exam_id, scan.copy_id, scan.student_id, scan.status,
      scan.image_uri, scan.detected_answers, scan.confirmed_answers, scan.confidence_score,
      scan.answer_sheet_type, scan.scan_mode, scan.idempotency_key,
      scan.session_id, scan.page_index, scan.total_pages, scan.created_at,
      scan.synced_at, scan.server_scan_id, scan.payload_json,
    ]
  );
}

export async function updateScanStatus(localId: string, status: string, extras?: Partial<LocalScanRow>): Promise<void> {
  const database = await getDatabase();
  const sets = ['status = ?'];
  const params: any[] = [status];

  if (extras?.synced_at) {
    sets.push('synced_at = ?');
    params.push(extras.synced_at);
  }
  if (extras?.server_scan_id) {
    sets.push('server_scan_id = ?');
    params.push(extras.server_scan_id);
  }
  if (extras?.confirmed_answers) {
    sets.push('confirmed_answers = ?');
    params.push(extras.confirmed_answers);
  }

  params.push(localId);
  await database.runAsync(`UPDATE local_scans SET ${sets.join(', ')} WHERE local_id = ?`, params);
}

export async function getAllScans(): Promise<LocalScanRow[]> {
  const database = await getDatabase();
  return database.getAllAsync<LocalScanRow>('SELECT * FROM local_scans ORDER BY created_at DESC');
}

export async function getPendingScans(): Promise<LocalScanRow[]> {
  const database = await getDatabase();
  return database.getAllAsync<LocalScanRow>(
    "SELECT * FROM local_scans WHERE status IN ('confirmed', 'review') ORDER BY created_at ASC"
  );
}

export async function deleteScan(localId: string): Promise<void> {
  const database = await getDatabase();
  await database.withTransactionAsync(async () => {
    await database.runAsync('DELETE FROM sync_queue WHERE local_id = ?', [localId]);
    await database.runAsync('DELETE FROM dead_letter WHERE local_id = ?', [localId]);
    await database.runAsync('DELETE FROM local_scans WHERE local_id = ?', [localId]);
  });
}

/* ── Sync Queue Operations ── */

export interface SyncQueueItem {
  local_id: string;
  retry_count: number;
  next_retry_at: string | null;
  last_error: string | null;
  created_at: string;
}

export async function addToSyncQueue(localId: string): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    `INSERT OR IGNORE INTO sync_queue (local_id, retry_count, created_at)
     VALUES (?, 0, ?)`,
    [localId, new Date().toISOString()]
  );
}

export async function getSyncQueue(): Promise<SyncQueueItem[]> {
  const database = await getDatabase();
  return database.getAllAsync<SyncQueueItem>(
    'SELECT * FROM sync_queue ORDER BY created_at ASC'
  );
}

export async function updateSyncQueueItem(
  localId: string,
  retryCount: number,
  nextRetryAt: string,
  lastError: string
): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    'UPDATE sync_queue SET retry_count = ?, next_retry_at = ?, last_error = ? WHERE local_id = ?',
    [retryCount, nextRetryAt, lastError, localId]
  );
}

export async function removeFromSyncQueue(localId: string): Promise<void> {
  const database = await getDatabase();
  await database.runAsync('DELETE FROM sync_queue WHERE local_id = ?', [localId]);
}

export async function moveToDeadLetter(localId: string, retryCount: number, lastError: string): Promise<void> {
  const database = await getDatabase();
  await database.runAsync(
    `INSERT OR REPLACE INTO dead_letter (local_id, retry_count, last_error, moved_at) VALUES (?, ?, ?, ?)`,
    [localId, retryCount, lastError, new Date().toISOString()]
  );
  await database.runAsync('DELETE FROM sync_queue WHERE local_id = ?', [localId]);
}

export async function getSyncQueueCount(): Promise<number> {
  const database = await getDatabase();
  const row = await database.getFirstAsync<{ count: number }>('SELECT COUNT(*) as count FROM sync_queue');
  return row?.count ?? 0;
}
