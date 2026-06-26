import.meta.glob([
  '../images/**',
  '../fonts/**',
])

async function initApp() {
  const Alpine = (await import('alpinejs')).default

  Alpine.data('themeManager', () => ({
    dark: localStorage.getItem('theme') === 'dark' ||
      (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
    init() {
      this.applyTheme()
      this.$watch('dark', () => this.applyTheme())
    },
    toggle() {
      this.dark = !this.dark
    },
    applyTheme() {
      document.documentElement.classList.toggle('dark', this.dark)
      localStorage.setItem('theme', this.dark ? 'dark' : 'light')
    },
  }))

  Alpine.data('authModal', () => ({
    open: false,
    tab: 'login',
    email: '',
    password: '',
    name: '',
    error: '',
    loading: false,
    async submit() {
      this.loading = true
      this.error = ''
      try {
        const endpoint = this.tab === 'login' ? '/api/auth/login' : '/api/auth/register'
        const body = this.tab === 'login'
          ? { email: this.email, password: this.password }
          : { name: this.name, email: this.email, password: this.password }

        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(body),
        })

        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'Authentication failed')

        localStorage.setItem('token', data.data?.token || data.token)
        window.location.reload()
      } catch (e) {
        this.error = e.message
      } finally {
        this.loading = false
      }
    },
    switchTab(tab) {
      this.tab = tab
      this.error = ''
    },
    close() {
      this.open = false
      this.error = ''
    },
  }))

  Alpine.start()

  const sentry = await import('./sentry.js')
  sentry.initSentry()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}
