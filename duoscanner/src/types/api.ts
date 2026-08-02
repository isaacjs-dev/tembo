export interface LoginRequest {
  email: string;
  password: string;
  device_name: string;
}

export interface LoginResponse {
  token: string;
  user: UserData;
}

export interface UserData {
  id: number;
  name: string;
  email: string;
  type: string;
  organization: {
    id: number;
    name: string;
  } | null;
}

export interface ApiError {
  error?: string;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface ScanUploadRequest {
  exam_id: number;
  copy_id?: number;
  image: {
    uri: string;
    type: string;
    name: string;
  };
  detected_answers: string; // JSON string
  confirmed_answers?: string; // JSON string
  student_id?: number;
  confidence_score: number;
  idempotency_key: string;
}

export interface ScanResponse {
  page: {
    id: number;
    session_id: string;
    exam_id: number;
    copy_id: number | null;
    page_index: number;
    total_pages: number;
    status: string;
    raw_answers: Record<string, number | null> | null;
    raw_confidences: Record<string, number> | null;
    overall_confidence: number;
    created_at: string;
  };
  progress: {
    uploaded: number;
    total: number;
    is_complete: boolean;
  };
  duplicate?: boolean;
}
