import { getSecret } from './supabase.ts'

export interface WebhookPayload {
  type: string
  table?: string
  record?: Record<string, unknown>
  old_record?: Record<string, unknown>
  schema?: string
  trigger?: string
  event?: string
  data?: Record<string, unknown>
}

export function parseBody(req: Request): Promise<WebhookPayload> {
  return req.json()
}

export function getSignature(req: Request): string | null {
  return req.headers.get('x-webhook-signature')
}

export function getTimestamp(req: Request): number | null {
  const ts = req.headers.get('x-webhook-timestamp')
  return ts ? parseInt(ts, 10) : null
}

export function getEventType(req: Request): string | null {
  return req.headers.get('x-webhook-event')
}

export async function verifySupabaseWebhook(
  payload: string,
  signature: string | null,
): Promise<boolean> {
  if (!signature) return false

  const webhookSecret = await getSecret('SUPABASE_WEBHOOK_SECRET')
  if (!webhookSecret) {
    console.warn('SUPABASE_WEBHOOK_SECRET not configured, skipping verification')
    return true
  }

  const encoder = new TextEncoder()
  const key = await crypto.subtle.importKey(
    'raw',
    encoder.encode(webhookSecret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['verify'],
  )

  const expectedSignatureBytes = await crypto.subtle.sign(
    'HMAC',
    key,
    encoder.encode(payload),
  )

  const expectedSignature = Array.from(new Uint8Array(expectedSignatureBytes))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('')

  return expectedSignature === signature
}

export function validateTimestamp(timestamp: number | null, maxAge = 300): boolean {
  if (!timestamp) return false
  const now = Math.floor(Date.now() / 1000)
  return now - timestamp <= maxAge
}

export function success(data: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify({ success: true, ...data }), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

export function error(message: string, status = 400): Response {
  return new Response(JSON.stringify({ success: false, error: message }), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}
