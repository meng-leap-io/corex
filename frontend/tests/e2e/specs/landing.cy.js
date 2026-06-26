/// <reference types="cypress" />

describe('Landing Page', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('loads successfully', () => {
    cy.title().should('not.be.empty')
    cy.contains('Corex').should('be.visible')
  })

  it('shows navigation bar', () => {
    cy.get('nav').should('be.visible')
    cy.contains('Features').should('be.visible')
    cy.contains('Pricing').should('be.visible')
  })

  it('displays hero section', () => {
    cy.get('[data-testid="hero-section"]').should('be.visible')
    cy.contains(/AI.{0,10}Development|Code|Platform/i).should('be.visible')
  })

  it('shows feature cards', () => {
    cy.get('[data-testid="features-grid"]').should('be.visible')
    cy.get('[data-testid="feature-card"]').should('have.length.at.least', 3)
  })

  it('pricing section displays plans', () => {
    cy.contains('Pricing').click()
    cy.get('[data-testid="pricing-tables"]').should('be.visible')
    cy.contains('Free').should('be.visible')
    cy.contains('Pro').should('be.visible')
  })

  it('FAQ section is present', () => {
    cy.contains('FAQ').should('be.visible')
    cy.get('[data-testid="faq-section"]').should('be.visible')
  })

  it('footer contains links', () => {
    cy.get('footer').should('be.visible')
    cy.get('footer a').should('have.length.at.least', 3)
  })

  it('has working navigation links', () => {
    cy.contains('About').click()
    cy.url().should('include', '/about')

    cy.contains('Contact').click()
    cy.url().should('include', '/contact')
  })

  it('auth modal opens on login click', () => {
    cy.contains('Sign In').click()
    cy.get('[data-testid="auth-modal"]').should('be.visible')
    cy.contains('Login').should('be.visible')
    cy.contains('Register').should('be.visible')
  })
})
