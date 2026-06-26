const CACHE_VERSION = 'v1'
const STATIC_CACHE = `corex-static-${CACHE_VERSION}`
const API_CACHE = `corex-api-${CACHE_VERSION}`
const CDN_CACHE = `corex-cdn-${CACHE_VERSION}`

const PRECACHE_URLS = [
  '/',
  '/features',
  '/pricing',
  '/offline',
]

const CDN_URLS = [
  'https://cdn.tailwindcss.com',
  'https://cdn.jsdelivr.net',
  'https://cdnjs.cloudflare.com',
  'https://unpkg.com',
  'https://fonts.googleapis.com',
  'https://fonts.gstatic.com',
  'https://browser.sentry-cdn.com',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(PRECACHE_URLS)
    })
  )
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name.startsWith('corex-') && name !== STATIC_CACHE && name !== API_CACHE && name !== CDN_CACHE)
          .map((name) => caches.delete(name))
      )
    })
  )
  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  if (request.method !== 'GET') return

  if (CDN_URLS.some((cdnUrl) => url.origin.startsWith(cdnUrl))) {
    event.respondWith(cacheFirst(request, CDN_CACHE))
    return
  }

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(request, API_CACHE))
    return
  }

  if (url.pathname.startsWith('/build/')) {
    event.respondWith(cacheFirst(request, STATIC_CACHE))
    return
  }

  if (url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|ico|woff2?)$/)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE))
    return
  }

  event.respondWith(networkFirst(request, STATIC_CACHE))
})

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request)
  if (cached) return cached

  try {
    const response = await fetch(request)
    if (response.ok) {
      const cache = await caches.open(cacheName)
      cache.put(request, response.clone())
    }
    return response
  } catch (error) {
    const fallback = await caches.match('/offline')
    if (fallback) return fallback
    return new Response('Offline', { status: 503 })
  }
}

async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request)
    if (response.ok) {
      const cache = await caches.open(cacheName)
      cache.put(request, response.clone())
    }
    return response
  } catch (error) {
    const cached = await caches.match(request)
    if (cached) return cached

    if (request.mode === 'navigate') {
      const fallback = await caches.match('/offline')
      if (fallback) return fallback
    }

    return new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    })
  }
}
