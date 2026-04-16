import { mount } from '@cypress/vue'
import ProductEditModal from '../../../src/views/ProductEditModal.vue'

describe('ProductEditModal.vue', () => {
  const defaultProduct = {
    id: 1,
    name: 'Test Product',
    pricing: [{ id: 1, price: 9.99 }],
    status: true,
    category_id: 2,
    image: ''
  }

  const categories = [
    { id: 1, name: 'Category 1' },
    { id: 2, name: 'Category 2' }
  ]

  it('renders modal when isOpen is true', () => {
    mount(ProductEditModal, {
      props: {
        isOpen: true,
        product: defaultProduct
      },
      data() {
        return {
          categories
        }
      }
    })
    cy.get('.modal.is-active').should('exist')
    cy.get('.modal-card-title').contains('Éditer le produit')
  })

  it('does not render modal when isOpen is false', () => {
    mount(ProductEditModal, {
      props: {
        isOpen: false,
        product: defaultProduct
      }
    })
    cy.get('.modal.is-active').should('not.exist')
  })

  it('binds form inputs to product data', () => {
    mount(ProductEditModal, {
      props: {
        isOpen: true,
        product: defaultProduct
      },
      data() {
        return {
          categories
        }
      }
    })
    cy.get('input[type="text"]').should('have.value', defaultProduct.name)
    cy.get('input[type="number"]').should('have.value', defaultProduct.pricing[0].price)
    cy.get('input[type="checkbox"]').should('be.checked')
    cy.get('select').should('have.value', defaultProduct.category_id.toString())
  })

  it('emits close event when close button clicked', () => {
    const closeSpy = cy.spy()
    mount(ProductEditModal, {
      props: {
        isOpen: true,
        product: defaultProduct,
        onClose: closeSpy
      }
    })
    cy.get('button.delete').click()
    cy.wrap(closeSpy).should('have.been.called')
  })

  it('shows validation alert if name is empty on save', () => {
    cy.on('window:alert', (txt) => {
      expect(txt).to.contains('Le nom du produit est requis')
    })
    mount(ProductEditModal, {
      props: {
        isOpen: true,
        product: { ...defaultProduct, name: '' }
      }
    })
    cy.get('button.is-success').click()
  })

  it('emits save and close events on successful save', () => {
    const saveSpy = cy.spy()
    const closeSpy = cy.spy()
    mount(ProductEditModal, {
      props: {
        isOpen: true,
        product: defaultProduct,
        onSave: saveSpy,
        onClose: closeSpy
      }
    })
    cy.get('button.is-success').click()
    cy.wrap(saveSpy).should('have.been.called')
    cy.wrap(closeSpy).should('have.been.called')
  })
})
