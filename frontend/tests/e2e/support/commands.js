/// <reference types="cypress" />

Cypress.Commands.add('login', (email, password) => {
  cy.request({
    method: 'POST',
    url: `${Cypress.env('API_URL')}/api/auth/login`,
    body: { email, password },
  }).then((resp) => {
    window.localStorage.setItem('auth_token', resp.body.token)
  })
})

Cypress.Commands.add('register', (name, email, password) => {
  cy.request({
    method: 'POST',
    url: `${Cypress.env('API_URL')}/api/auth/register`,
    body: { name, email, password, password_confirmation: password },
  })
})

Cypress.Commands.add('dataCy', (value) => {
  cy.get(`[data-testid="${value}"]`)
})
