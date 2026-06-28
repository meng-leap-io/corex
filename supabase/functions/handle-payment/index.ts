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

interface StripeEvent {
  id: string
  type: string
  data: {
    object: {
      id: string
      customer?: string
      customer_email?: string
      status?: string
      amount?: number
      currency?: string
      interval?: string
      current_period_start?: number
      current_period_end?: number
      cancel_at_period_end?: boolean
      metadata?: Record<string, string>
      email?: string
    }
  }
  created: number
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('handle-payment')
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

    const event = body as StripeEvent
    const object = event.data?.object

    if (!event.type || !object) {
      return error('Invalid Stripe event format', 400)
    }

    await supabase.from('webhook_logs').insert({
      provider: 'stripe',
      event_type: event.type,
      event_id: event.id,
      status: 'processing',
      payload: body,
      created_at: new Date().toISOString(),
    })

    switch (event.type) {
      case 'customer.subscription.created':
      case 'customer.subscription.updated': {
        const userId = object.metadata?.user_id ?? object.customer
        const status = mapStripeStatus(object.status ?? 'unknown')

        const { error: subError } = await supabase.from('subscriptions').upsert(
          {
            user_id: userId,
            stripe_id: object.id,
            stripe_status: object.status,
            plan: mapStripePlan(object),
            status,
            quantity: 1,
            trial_ends_at: object.current_period_end
              ? new Date(object.current_period_end * 1000).toISOString()
              : null,
            ends_at: object.cancel_at_period_end
              ? new Date(object.current_period_end * 1000).toISOString()
              : null,
          },
          { onConflict: 'stripe_id' },
        )

        if (subError) {
          logger.error('Failed to upsert subscription', subError)
        }

        if (object.customer_email) {
          await sendEmail({
            to: object.customer_email,
            subject: status === 'active'
              ? 'Subscription confirmed'
              : 'Subscription updated',
            html: `
              <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
                <h2 style="color: #6366f1;">Payment Confirmed</h2>
                <p>Your subscription has been ${status}.</p>
                <p>Plan: ${mapStripePlan(object)}</p>
                ${object.current_period_end
                  ? `<p>Next billing: ${new Date(object.current_period_end * 1000).toLocaleDateString()}</p>`
                  : ''}
              </div>
            `,
          })
        }
        break
      }

      case 'customer.subscription.deleted': {
        const { error: delError } = await supabase
          .from('subscriptions')
          .update({
            status: 'cancelled',
            cancelled_at: new Date().toISOString(),
          })
          .eq('stripe_id', object.id)

        if (delError) {
          logger.error('Failed to mark subscription cancelled', delError)
        }

        if (object.customer_email) {
          await sendEmail({
            to: object.customer_email,
            subject: 'Subscription cancelled',
            html: `
              <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
                <h2 style="color: #6366f1;">Subscription Cancelled</h2>
                <p>Your subscription has been cancelled.</p>
                <p>You will continue to have access until the end of your billing period.</p>
              </div>
            `,
          })
        }
        break
      }

      case 'invoice.payment_succeeded': {
        if (object.customer_email) {
          await sendEmail({
            to: object.customer_email,
            subject: 'Payment successful',
            html: `
              <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
                <h2 style="color: #6366f1;">Payment Successful</h2>
                <p>Amount: $${((object.amount ?? 0) / 100).toFixed(2)} ${(object.currency ?? 'usd').toUpperCase()}</p>
              </div>
            `,
          })
        }
        break
      }

      case 'invoice.payment_failed': {
        if (object.customer_email) {
          await sendEmail({
            to: object.customer_email,
            subject: 'Payment failed — action required',
            html: `
              <div style="font-family: system-ui; max-width: 480px; margin: 0 auto;">
                <h2 style="color: #dc2626;">Payment Failed</h2>
                <p>Your recent payment of $${((object.amount ?? 0) / 100).toFixed(2)} could not be processed.</p>
                <p>Please update your payment method to avoid service interruption.</p>
              </div>
            `,
          })
        }
        break
      }

      default:
        logger.info('Unhandled Stripe event', { event_type: event.type })
    }

    await supabase
      .from('webhook_logs')
      .update({ status: 'processed' })
      .eq('event_id', event.id)

    logger.info('Payment event handled', {
      event_type: event.type,
      stripe_id: object.id,
      duration: endTimer(),
    })

    return success({ handled: true, event_type: event.type })
  } catch (err) {
    logger.error('Failed to handle payment event', err as Error)
    return error(`Payment handling failed: ${(err as Error).message}`, 500)
  }
})

function mapStripeStatus(status: string): string {
  switch (status) {
    case 'active':
    case 'trialing':
      return 'active'
    case 'past_due':
    case 'incomplete':
      return 'past_due'
    case 'canceled':
    case 'unpaid':
      return 'cancelled'
    default:
      return 'unknown'
  }
}

function mapStripePlan(object: {
  items?: { data?: Array<{ price?: { nickname?: string; product?: string } }> }
  metadata?: Record<string, string>
  plan?: { nickname?: string }
}): string {
  return object.metadata?.plan
    ?? object.plan?.nickname
    ?? object.items?.data?.[0]?.price?.nickname
    ?? object.items?.data?.[0]?.price?.product
    ?? 'free'
}
