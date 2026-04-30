import { defineConfig } from 'cypress'

export default defineConfig({
  component: {
    devServer: {
      framework: 'vue',
      bundler: 'vite',
    },
    specPattern: 'cypress/component/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'cypress/support/component.js',
  },
  e2e: {
    // e2e test config if needed
  },
})
