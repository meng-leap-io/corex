import { corsHeaders, handleCors } from '../_shared/cors.ts'
import { Logger } from '../_shared/logger.ts'
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

interface AnalyticsEvent {
  event_type: 'api_call' | 'ai_generation' | 'file_upload' | 'login' | 'project_create' | 'export'
  user_id: string
  metadata?: Record<string, unknown>
  value?: number
  timestamp?: string
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('update-analytics')
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

    const events: AnalyticsEvent[] = Array.isArray(body.record)
      ? body.record
      : [body.record ?? body.data]

    const results: Array<{ event_type: string; status: string }> = []

    for (const event of events) {
      if (!event?.event_type || !event?.user_id) {
        results.push({ event_type: event?.event_type ?? 'unknown', status: 'skipped' })
        continue
      }

      const { error: insertError } = await supabase.from('analytics_events').insert({
        event_type: event.event_type,
        user_id: event.user_id,
        metadata: event.metadata ?? {},
        value: event.value ?? 1,
        created_at: event.timestamp ?? new Date().toISOString(),
      })

      if (insertError) {
        logger.warn(`Failed to insert analytics event: ${event.event_type}`, insertError)
        results.push({ event_type: event.event_type, status: 'error' })
        continue
      }

      const { error: aggError } = await supabase.rpc('increment_daily_metric', {
        p_event_type: event.event_type,
        p_user_id: event.user_id,
        p_value: event.value ?? 1,
      })

      if (aggError) {
        logger.warn('Failed to update daily aggregate', aggError)
      }

      const { error: userUpdateError } = await supabase
        .from('users')
        .update({
          last_active_at: new Date().toISOString(),
        })
        .eq('id', event.user_id)

      if (userUpdateError) {
        logger.warn('Failed to update user last_active_at', userUpdateError)
      }

      results.push({ event_type: event.event_type, status: 'recorded' })
    }

    logger.info('Analytics updated', {
      count: events.length,
      results,
      duration: endTimer(),
    })

    return success({ recorded: events.length, results })
  } catch (err) {
    logger.error('Failed to update analytics', err as Error)
    return error(`Analytics update failed: ${(err as Error).message}`, 500)
  }
})
