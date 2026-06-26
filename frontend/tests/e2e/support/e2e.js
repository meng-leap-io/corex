/// <reference types="cypress" />

import './commands'

beforeEach(() => {
  cy.on('uncaught:exception', (err) => {
    console.error('Uncaught exception:', err.message)
    return false
  })
})
