const { defineConfig } = require('cypress')

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8080',
    supportFile: 'tests/e2e/support/e2e.js',
    specPattern: 'tests/e2e/specs/**/*.cy.{js,jsx,ts,tsx}',
    viewportWidth: 1440,
    viewportHeight: 900,
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 30000,
    screenshotsFolder: 'tests/e2e/screenshots',
    videosFolder: 'tests/e2e/videos',
    downloadsFolder: 'tests/e2e/downloads',
    trashAssetsBeforeRuns: true,
    video: false,
    screenshotOnRunFailure: true,
    chromeWebSecurity: false,
    watchForFileChanges: false,
    retries: {
      runMode: 2,
      openMode: 0,
    },
    env: {
      API_URL: 'http://localhost:8000',
      APP_URL: 'http://localhost:8080',
      testEmail: 'cypress@corex.dev',
      testPassword: 'TestPass123!',
    },
  },

  component: {
    supportFile: 'tests/component/support/component.js',
    specPattern: 'tests/component/**/*.cy.{js,jsx,ts,tsx}',
    devServer: {
      framework: 'vue',
      bundler: 'vite',
    },
  },

  reporter: 'mochawesome',
  reporterOptions: {
    reportDir: 'tests/e2e/reports',
    overwrite: false,
    html: false,
    json: true,
  },
})
