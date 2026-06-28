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

interface AICompletionEvent {
  conversation_id: string
  user_id: string
  model: string
  provider: string
  prompt: string
  completion: string
  tokens_prompt: number
  tokens_completion: number
  duration_ms: number
  cost?: number
}

Deno.serve(async (req: Request) => {
  const cors = handleCors(req)
  if (cors) return cors

  const logger = new Logger('process-ai')
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

    const event = (body.record ?? body.data) as AICompletionEvent
    if (!event?.conversation_id || !event?.user_id) {
      return error('Missing required fields: conversation_id, user_id', 400)
    }

    const { error: usageError } = await supabase.from('ai_usage_logs').insert({
      user_id: event.user_id,
      provider: event.provider ?? 'unknown',
      model: event.model,
      prompt_tokens: event.tokens_prompt ?? 0,
      completion_tokens: event.tokens_completion ?? 0,
      cost: event.cost ?? 0,
      duration: event.duration_ms ?? null,
      success: true,
    })

    if (usageError) {
      logger.error('Failed to log AI usage', usageError)
    }

    const tokenCount = (event.tokens_prompt ?? 0) + (event.tokens_completion ?? 0)
    const { error: updateError } = await supabase.rpc('increment_conversation_tokens', {
      conv_id: event.conversation_id,
      token_delta: tokenCount,
    })

    if (updateError) {
      logger.warn('Failed to update conversation tokens', {
        conversation_id: event.conversation_id,
        error: updateError,
      })
    }

    const functionName = extractFunctionName(event.prompt)
    if (functionName) {
      const { error: genError } = await supabase.from('code_generations').insert({
        user_id: event.user_id,
        prompt: event.prompt,
        code_generated: event.completion,
        language: detectLanguage(event.completion),
        model_used: event.model,
        tokens_used: tokenCount,
        cost: event.cost ?? 0,
        status: 'completed',
      })

      if (genError) {
        logger.warn('Failed to save code generation', genError)
      }
    }

    if (event.cost && event.cost > 0) {
      const { error: costError } = await supabase.rpc('track_ai_spend', {
        p_user_id: event.user_id,
        p_cost: event.cost,
        p_model: event.model,
      })

      if (costError) {
        logger.warn('Failed to track AI spend', costError)
      }
    }

    logger.info('AI response processed', {
      conversation_id: event.conversation_id,
      model: event.model,
      tokens: tokenCount,
      duration: endTimer(),
    })

    return success({
      processed: true,
      conversation_id: event.conversation_id,
      tokens: tokenCount,
    })
  } catch (err) {
    logger.error('Failed to process AI response', err as Error)
    return error(`AI processing failed: ${(err as Error).message}`, 500)
  }
})

function extractFunctionName(prompt: string): string | null {
  const match = prompt.match(
    /(?:function|fn|def|func|const\s+\w+\s*=\s*(?:async)?)\s+(\w+)/i,
  )
  return match?.[1] ?? null
}

function detectLanguage(code: string): string {
  if (/^(import|export|function|const|let|var|class)/m.test(code)) {
    return 'javascript'
  }
  if (/^(use|fn|pub|impl|let|mut)/m.test(code)) {
    return 'rust'
  }
  if (/^(def|class|import|from)/m.test(code)) {
    return 'python'
  }
  if (/^(<\?php|namespace|use|class|function)/m.test(code)) {
    return 'php'
  }
  if (/^(package|import|func|type)/m.test(code)) {
    return 'go'
  }
  return 'unknown'
}
