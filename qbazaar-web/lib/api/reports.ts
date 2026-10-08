/**
 * Typed client for the reports domain (Sprint 10).
 *
 * The reports endpoint accepts a polymorphic target (ad/user/conversation/
 * message) plus a category enum. The backend rate-limits duplicate reports
 * per target/category which surfaces as the `REPORT_002` code
 * the UI maps to a friendly toast.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  MakeReportRequest,
  Report,
  SuccessEnvelope,
} from './types';

const REPORTS_BASE = '/api/v1/reports';

function toApiClientError(err: unknown): ApiClientError {
  if (isAxiosError<ErrorEnvelope>(err) && err.response?.data?.error) {
    const e = err.response.data.error;
    return new ApiClientError({
      status: err.response.status,
      code: e.code,
      messageKey: e.message_key,
      message: e.message,
      details: e.details,
      requestId: e.request_id,
    });
  }
  if (err instanceof Error) {
    return new ApiClientError({
      status: 0,
      code: 'NETWORK_ERROR',
      messageKey: 'errors.network',
      message: err.message,
    });
  }
  return new ApiClientError({
    status: 0,
    code: 'UNKNOWN_ERROR',
    messageKey: 'errors.unknown',
    message: 'Unknown error',
  });
}

export async function submitReport(
  payload: MakeReportRequest,
): Promise<Report> {
  try {
    const { data } = await api.post<SuccessEnvelope<Report>>(
      REPORTS_BASE,
      payload,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
