import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.39.0'

const supabaseUrl = Deno.env.get('SUPABASE_URL') ?? ''
const supabaseServiceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''

export const supabase = createClient(supabaseUrl, supabaseServiceKey)

export async function getSecret(name: string): Promise<string | null> {
  try {
    const { data, error } = await supabase.rpc('get_secret', { secret_name: name })
    if (error) throw error
    return data
  } catch {
    return Deno.env.get(name) ?? null
  }
}
