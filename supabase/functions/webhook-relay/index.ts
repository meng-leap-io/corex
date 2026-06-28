import { corsHeaders, handleCors } from '../_shared/cors.ts'
import { Logger } from '../_shared/logger.ts'
import { supabase, getSecret } from '../_shared/supabase.ts'
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

interface RelayTarget {
  name: string
  url: string
  secret?: string
  events: string[]
  retry_count?: number
  timeout_ms?: number
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('webhook-relay')
  const endTimer = logger.startTimer()

  try {
    const body = await parseBody(req)
    const signature = getSignature(req)
    const timestamp = getTimestamp(req)
    const eventType = getEventType(req) ?? body.type

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

    if (!eventType) {
      return error('Missing event type', 400)
    }

    const { data: targets, error: targetsError } = await supabase
      .from('webhook_endpoints')
      .select('*')
      .overlaps('events', [eventType, '*'])

    if (targetsError) {
      logger.error('Failed to fetch webhook targets', targetsError)
      return error('Failed to resolve relay targets', 500)
    }

    if (!targets || targets.length === 0) {
      logger.info('No relay targets configured for event', { event_type: eventType })
      return success({ relayed: 0, targets: [] })
    }

    const results: Array<{ target: string; status: number; duration: number }> = []

    for (const target of targets) {
      const laravelUrl = await getSecret('LARAVEL_WEBHOOK_URL') ?? ''
      const targetSecret = target.secret ?? await getSecret('LARAVEL_WEBHOOK_SECRET') ?? ''

      const payload = JSON.stringify({
        type: eventType,
        source: 'supabase-webhook-relay',
        table: body.table,
        schema: body.schema,
        record: body.record,
        old_record: body.old_record,
        data: body.data,
        metadata: {
          relayed_at: new Date().toISOString(),
          relay_function: 'webhook-relay',
        },
      })

      const encoder = new TextEncoder()
      const key = await crypto.subtle.importKey(
        'raw',
        encoder.encode(targetSecret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign'],
      )

      const hmacBytes = await crypto.subtle.sign('HMAC', key, encoder.encode(payload))

      const computedSignature = Array.from(new Uint8Array(hmacBytes))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('')

      const targetUrl = target.url?.replace('{event_type}', eventType)

      const maxRetries = target.retry_count ?? 3
      let lastError: Error | null = null

      for (let attempt = 0; attempt < maxRetries; attempt++) {
        if (attempt > 0) {
          const backoff = Math.min(1000 * Math.pow(2, attempt), 30000)
          await new Promise((r) => setTimeout(r, backoff))
        }

        try {
          const controller = new AbortController()
          const timeout = setTimeout(
            () => controller.abort(),
            target.timeout_ms ?? 10000,
          )

          const relayStart = performance.now()
          const relayResponse = await fetch(targetUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Webhook-Signature': computedSignature,
              'X-Webhook-Timestamp': String(Math.floor(Date.now() / 1000)),
              'X-Webhook-Event': eventType,
              'X-Webhook-Attempt': String(attempt + 1),
              'X-Webhook-Source': 'supabase',
              'User-Agent': 'SupabaseEdgeFunction/1.0',
            },
            body: payload,
            signal: controller.signal,
          })

          clearTimeout(timeout)
          const relayDuration = performance.now() - relayStart

          results.push({
            target: target.name,
            status: relayResponse.status,
            duration: relayDuration,
          })

          if (relayResponse.ok) {
            logger.info('Webhook relayed successfully', {
              target: target.name,
              event_type: eventType,
              attempt: attempt + 1,
              duration: relayDuration,
            })
            lastError = null
            break
          } else {
            lastError = new Error(`HTTP ${relayResponse.status}: ${await relayResponse.text()}`)
          }
        } catch (err) {
          lastError = err instanceof Error ? err : new Error(String(err))
          logger.warn(`Relay attempt ${attempt + 1} failed`, {
            target: target.name,
            error: lastError.message,
          })
        }
      }

      if (lastError) {
        logger.error('All relay retries exhausted', {
          target: target.name,
          error: lastError.message,
          retries: maxRetries,
        })
      }
    }

    logger.info('Webhook relay completed', {
      event_type: eventType,
      targets: results.length,
      duration: endTimer(),
    })

    return success({ relayed: results.length, targets: results })
  } catch (err) {
    logger.error('Webhook relay failed', err as Error)
    return error(`Relay failed: ${(err as Error).message}`, 500)
  }
})
