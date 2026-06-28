import { getSecret } from './supabase.ts'

export interface EmailPayload {
  to: string | string[]
  subject: string
  html?: string
  text?: string
  from?: string
  replyTo?: string
  cc?: string | string[]
  bcc?: string | string[]
  attachments?: Array<{
    filename: string
    content: string
    contentType?: string
  }>
}

export async function sendEmail(payload: EmailPayload): Promise<Response> {
  const apiKey = await getSecret('RESEND_API_KEY')
  if (!apiKey) {
    throw new Error('RESEND_API_KEY not configured')
  }

  const defaultFrom = await getSecret('EMAIL_FROM') ?? 'noreply@corex.dev'

  const response = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: payload.from ?? defaultFrom,
      to: Array.isArray(payload.to) ? payload.to : [payload.to],
      subject: payload.subject,
      html: payload.html,
      text: payload.text,
      reply_to: payload.replyTo,
      cc: payload.cc,
      bcc: payload.bcc,
      attachments: payload.attachments,
    }),
  })

  if (!response.ok) {
    const error = await response.text()
    throw new Error(`Resend API error (${response.status}): ${error}`)
  }

  return response
}
