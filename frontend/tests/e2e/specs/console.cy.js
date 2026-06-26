/// <reference types="cypress" />

describe('AI Console IDE', () => {
  beforeEach(() => {
    cy.visit('http://localhost:8080/console')
  })

  it('loads the console page', () => {
    cy.title().should('not.be.empty')
    cy.get('[data-testid="console-container"]').should('be.visible')
  })

  it('shows three main panels', () => {
    cy.get('[data-testid="editor-panel"]').should('be.visible')
    cy.get('[data-testid="chat-panel"]').should('be.visible')
    cy.get('[data-testid="terminal-panel"]').should('be.visible')
  })

  it('file explorer is visible', () => {
    cy.get('[data-testid="file-explorer"]').should('be.visible')
    cy.contains('Files').should('be.visible')
  })

  it('can type in chat input', () => {
    cy.get('[data-testid="chat-input"]').type('Write a Laravel controller')
    cy.get('[data-testid="chat-input"]').should('have.value', 'Write a Laravel controller')
  })

  it('send button enables with text', () => {
    cy.get('[data-testid="chat-input"]').type('Hello')
    cy.get('[data-testid="send-button"]').should('not.be.disabled')
  })

  it('send button disabled when empty', () => {
    cy.get('[data-testid="send-button"]').should('be.disabled')
  })

  it('terminal displays prompt', () => {
    cy.get('[data-testid="terminal-panel"]').should('contain.text', '$')
    cy.get('[data-testid="terminal-input"]').should('exist')
  })

  it('settings button opens panel', () => {
    cy.get('[data-testid="settings-button"]').click()
    cy.get('[data-testid="settings-panel"]').should('be.visible')
  })

  it('settings has theme options', () => {
    cy.get('[data-testid="settings-button"]').click()
    cy.contains('Appearance').should('be.visible')
    cy.contains('Dark').should('be.visible')
    cy.contains('Light').should('be.visible')
  })

  it('can toggle AI commands', () => {
    cy.get('[data-testid="chat-input"]').type('/explain')
    cy.get('[data-testid="command-suggestions"]').should('be.visible')
  })

  it('tab switching between panels works', () => {
    cy.get('[data-testid="tab-terminal"]').click()
    cy.get('[data-testid="terminal-panel"]').should('be.visible')

    cy.get('[data-testid="tab-editor"]').click()
    cy.get('[data-testid="editor-panel"]').should('be.visible')
  })
})
