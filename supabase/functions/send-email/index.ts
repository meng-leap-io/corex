import { corsHeaders, handleCors } from '../_shared/cors.ts'
import { Logger } from '../_shared/logger.ts'
import { sendEmail } from '../_shared/resend.ts'
import { supabase } from '../_shared/supabase.ts'
import {
  error,
  getEventType,
  getSignature,
  getTimestamp,
  parseBody,
  success,
  validateTimestamp,
  verifySupabaseWebhook,
} from '../_shared/verify.ts'

interface NotificationEvent {
  type: 'message' | 'project_update' | 'team_invite' | 'system' | 'ai_complete'
  user_id: string
  title: string
  body?: string
  priority: 'low' | 'normal' | 'high' | 'urgent'
  data?: Record<string, unknown>
  channel?: string
  event?: string
}

interface EmailTemplate {
  subject: string
  html: string
}

const EMAIL_TEMPLATES: Record<string, (data: NotificationEvent) => EmailTemplate> = {
  message: (data) => ({
    subject: `New message: ${data.title}`,
    html: `
      <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
        <h2 style="color: #6366f1;">New Message</h2>
        <p>${data.body ?? ''}</p>
        <p style="color: #666; font-size: 14px;">
          Priority: <strong>${data.priority}</strong>
        </p>
      </div>
    `,
  }),

  team_invite: (data) => ({
    subject: `Team invitation: ${data.title}`,
    html: `
      <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
        <h2 style="color: #6366f1;">Team Invitation</h2>
        <p>${data.body ?? ''}</p>
        <div style="margin: 24px 0;">
          <a href="${data.data?.invite_url ?? '#'}"
             style="background: #6366f1; color: white; padding: 12px 24px;
                    border-radius: 6px; text-decoration: none;">
            Accept Invitation
          </a>
        </div>
      </div>
    `,
  }),

  ai_complete: (data) => ({
    subject: `AI task complete: ${data.title}`,
    html: `
      <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
        <h2 style="color: #6366f1;">AI Task Complete</h2>
        <p>${data.body ?? ''}</p>
        ${data.data?.result ? `<pre style="background: #f5f5f5; padding: 12px; border-radius: 4px; font-size: 13px;">${JSON.stringify(data.data.result, null, 2)}</pre>` : ''}
      </div>
    `,
  }),

  system: (data) => ({
    subject: `System notification: ${data.title}`,
    html: `
      <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
        <h2 style="color: #6366f1;">System Notification</h2>
        <p>${data.body ?? ''}</p>
      </div>
    `,
  }),

  project_update: (data) => ({
    subject: `Project update: ${data.title}`,
    html: `
      <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
        <h2 style="color: #6366f1;">Project Update</h2>
        <p>${data.body ?? ''}</p>
        ${data.data?.change_summary ? `<p style="color: #666; font-size: 13px;">${data.data.change_summary}</p>` : ''}
      </div>
    `,
  }),
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('send-email')
  const endTimer = logger.startTimer()

  try {
    const body = await parseBody(req)
    const signature = getSignature(req)
    const timestamp = getTimestamp(req)

    if (!validateTimestamp(timestamp)) {
      return error('Request timestamp is invalid or expired', 400)
    }

    const isValid = await verifySupabaseWebhook(
      JSON.stringify(body),
      signature,
    )
    if (!isValid) {
      return error('Invalid webhook signature', 401)
    }

    const event = body.record as NotificationEvent
    if (!event?.user_id || !event?.type) {
      return error('Missing required fields: user_id, type', 400)
    }

    const { data: user } = await supabase
      .from('users')
      .select('email, name, notification_settings')
      .eq('id', event.user_id)
      .single()

    if (!user?.email) {
      return error('User not found or no email', 404)
    }

    const settings = user.notification_settings ?? {}
    if (settings.email_notifications === false) {
      logger.info('Email notifications disabled for user', {
        user_id: event.user_id,
        type: event.type,
      })
      return success({ skipped: true, reason: 'notifications_disabled' })
    }

    const template = EMAIL_TEMPLATES[event.type]
    if (!template) {
      return error(`Unknown notification type: ${event.type}`, 400)
    }

    const email = template(event)

    await sendEmail({
      to: user.email,
      subject: email.subject,
      html: email.html,
    })

    await supabase.from('notifications').insert({
      user_id: event.user_id,
      type: event.type,
      title: event.title,
      body: event.body ?? null,
      data: event.data ?? {},
      priority: event.priority ?? 'normal',
      channel: 'email',
      event: event.event ?? null,
    })

    logger.info('Email sent successfully', {
      user_id: event.user_id,
      type: event.type,
      recipient: user.email,
      duration: endTimer(),
    })

    return success({ sent: true, recipient: user.email })
  } catch (err) {
    logger.error('Failed to send email', err as Error)
    return error(`Failed to send email: ${(err as Error).message}`, 500)
  }
})
