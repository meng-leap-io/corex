/// <reference types="cypress" />

describe('Authentication Flow', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('opens auth modal from sign in button', () => {
    cy.contains('Sign In').click()
    cy.get('[data-testid="auth-modal"]').should('be.visible')
  })

  it('switches between login and register tabs', () => {
    cy.contains('Sign In').click()
    cy.contains('Register').click()
    cy.contains('Create Account').should('be.visible')

    cy.contains('Login').click()
    cy.contains('Welcome Back').should('be.visible')
  })

  it('validates empty form submission', () => {
    cy.contains('Sign In').click()
    cy.contains('Login').click()

    cy.get('[data-testid="login-form"]').within(() => {
      cy.get('button[type="submit"]').click()
    })

    cy.contains('required').should('be.visible')
  })

  it('validates email format', () => {
    cy.contains('Sign In').click()

    cy.get('input[type="email"]').type('not-an-email')
    cy.get('input[type="password"]').type('TestPass123!')

    cy.contains('Login').click()
    cy.contains('valid email').should('be.visible')
  })

  it('shows forgot password option', () => {
    cy.contains('Sign In').click()
    cy.contains('Forgot Password').should('be.visible')
  })

  it('can toggle password visibility', () => {
    cy.contains('Sign In').click()
    cy.get('input[type="password"]').type('secret123')
    cy.get('[data-testid="toggle-password"]').click()
    cy.get('input[type="text"]').should('have.value', 'secret123')
  })

  it('registration form has all required fields', () => {
    cy.contains('Sign In').click()
    cy.contains('Register').click()

    cy.get('input[name="name"]').should('be.visible')
    cy.get('input[type="email"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('input[name="password_confirmation"]').should('be.visible')
  })

  it('closes modal on escape', () => {
    cy.contains('Sign In').click()
    cy.get('[data-testid="auth-modal"]').should('be.visible')
    cy.get('body').type('{esc}')
    cy.get('[data-testid="auth-modal"]').should('not.exist')
  })
})
