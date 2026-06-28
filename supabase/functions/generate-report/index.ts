import { corsHeaders, handleCors } from '../_shared/cors.ts'
import { Logger } from '../_shared/logger.ts'
import { sendEmail } from '../_shared/resend.ts'
import { supabase } from '../_shared/supabase.ts'
import {
  error,
  getSignature,
  getTimestamp,
  parseBody,
  success,
  validateTimestamp,
  verifySupabaseWebhook,
} from '../_shared/verify.ts'

interface ReportRequest {
  type: 'daily' | 'weekly' | 'monthly' | 'custom'
  user_id?: string
  date_range?: { start: string; end: string }
  format?: 'json' | 'html' | 'csv'
  include_charts?: boolean
  channels?: ('email' | 'in_app')[]
}

interface UsageReport {
  period: string
  total_requests: number
  total_tokens: number
  total_cost: number
  models_used: Record<string, number>
  daily_breakdown: Array<{
    date: string
    requests: number
    tokens: number
    cost: number
  }>
  top_users: Array<{
    user_id: string
    email: string
    requests: number
    tokens: number
  }>
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('generate-report')
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

    const request = (body.record ?? body.data) as ReportRequest
    const period = request.type ?? 'daily'

    const { start, end } = request.date_range ?? getDateRange(period)

    const { data: usage, error: usageError } = await supabase.rpc(
      'get_usage_report',
      {
        p_start_date: start,
        p_end_date: end,
        p_user_id: request.user_id ?? null,
      },
    )

    if (usageError) {
      logger.error('Failed to fetch usage data', usageError)
      return error('Failed to generate report', 500)
    }

    const report: UsageReport = formatReport(usage, period, start, end)

    const outputFormat = request.format ?? 'json'
    const channels = request.channels ?? ['email']

    for (const channel of channels) {
      if (channel === 'email') {
        const { data: adminUsers } = await supabase
          .from('users')
          .select('email, name')
          .or('role.eq.admin,email.eq.admin@corex.dev')

        if (adminUsers) {
          for (const admin of adminUsers) {
            await sendEmail({
              to: admin.email,
              subject: `Corex Report: ${period} usage summary`,
              html: generateReportHtml(report, period),
            })
          }
        }

        if (request.user_id) {
          const { data: user } = await supabase
            .from('users')
            .select('email')
            .eq('id', request.user_id)
            .single()

          if (user?.email) {
            await sendEmail({
              to: user.email,
              subject: `Your ${period} usage report`,
              html: generateReportHtml(report, period),
            })
          }
        }
      }

      if (channel === 'in_app') {
        const notificationRecipients = request.user_id
          ? [{ user_id: request.user_id }]
          : (await supabase.from('users').select('id').in('role', ['admin', 'moderator'])).data ?? []

        for (const recipient of notificationRecipients) {
          await supabase.from('notifications').insert({
            user_id: recipient.user_id,
            type: 'system',
            title: `${period} report ready`,
            body: `Your ${period} usage report has been generated. Total cost: $${report.total_cost.toFixed(4)}`,
            data: { report, period },
            priority: 'normal',
          })
        }
      }
    }

    logger.info('Report generated', {
      period,
      channels,
      recipients: channels.length,
      duration: endTimer(),
    })

    return success({
      generated: true,
      period,
      report,
    })
  } catch (err) {
    logger.error('Failed to generate report', err as Error)
    return error(`Report generation failed: ${(err as Error).message}`, 500)
  }
})

function getDateRange(period: string): { start: string; end: string } {
  const end = new Date().toISOString()
  const start = new Date()

  switch (period) {
    case 'daily':
      start.setDate(start.getDate() - 1)
      break
    case 'weekly':
      start.setDate(start.getDate() - 7)
      break
    case 'monthly':
      start.setMonth(start.getMonth() - 1)
      break
    default:
      start.setDate(start.getDate() - 7)
  }

  return { start: start.toISOString(), end }
}

function formatReport(
  data: unknown,
  period: string,
  start: string,
  end: string,
): UsageReport {
  const rows = Array.isArray(data) ? data : []
  const modelsUsed: Record<string, number> = {}

  for (const row of rows) {
    const model = (row as Record<string, string>).model ?? 'unknown'
    modelsUsed[model] = (modelsUsed[model] ?? 0) + 1
  }

  return {
    period,
    total_requests: rows.length,
    total_tokens: rows.reduce(
      (sum, r) => sum + ((r as Record<string, number>).tokens ?? 0),
      0,
    ),
    total_cost: rows.reduce(
      (sum, r) => sum + ((r as Record<string, number>).cost ?? 0),
      0,
    ),
    models_used: modelsUsed,
    daily_breakdown: [],
    top_users: [],
  }
}

function generateReportHtml(report: UsageReport, period: string): string {
  return `
    <div style="font-family: system-ui; max-width: 600px; margin: 0 auto;">
      <h2 style="color: #6366f1;">${period.charAt(0).toUpperCase() + period.slice(1)} Usage Report</h2>
      <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr>
          <td style="padding: 8px; border-bottom: 1px solid #eee; color: #666;">Total Requests</td>
          <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">${report.total_requests}</td>
        </tr>
        <tr>
          <td style="padding: 8px; border-bottom: 1px solid #eee; color: #666;">Total Tokens</td>
          <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">${report.total_tokens.toLocaleString()}</td>
        </tr>
        <tr>
          <td style="padding: 8px; border-bottom: 1px solid #eee; color: #666;">Total Cost</td>
          <td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">$${report.total_cost.toFixed(4)}</td>
        </tr>
      </table>
      <p style="color: #999; font-size: 12px;">Generated at ${new Date().toISOString()}</p>
    </div>
  `
}
