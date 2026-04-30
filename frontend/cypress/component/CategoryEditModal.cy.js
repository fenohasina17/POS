import CategoryEditModal from '../../../src/views/CategoryEditModal.vue'

describe('CategoryEditModal.vue', () => {
  const defaultCategory = {
    id: 1,
    name: 'Test Category',
    description: 'Test Description',
    printer_type: 1,
  }

  it('renders modal when isOpen is true', () => {
    cy.mount(CategoryEditModal, {
      props: {
        isOpen: true,
        categoryData: defaultCategory,
      },
    })
    cy.get('.modal.is-active').should('exist')
    cy.get('.modal-card-title').contains('Modifier la catégorie')
  })

  it('does not render modal when isOpen is false', () => {
    cy.mount(CategoryEditModal, {
      props: {
        isOpen: false,
        categoryData: defaultCategory,
      },
    })
    cy.get('.modal.is-active').should('not.exist')
  })

  it('binds form inputs to category data', () => {
    cy.mount(CategoryEditModal, {
      props: {
        isOpen: true,
        categoryData: defaultCategory,
      },
    })
    cy.get('input[type="text"]').should('have.value', defaultCategory.name)
    cy.get('textarea').should('have.value', defaultCategory.description)
  })

  it('emits updated event with form data on submit', () => {
    const updatedSpy = cy.spy()
    cy.mount(CategoryEditModal, {
      props: {
        isOpen: true,
        categoryData: defaultCategory,
        onUpdated: updatedSpy,
      },
    })
    cy.get('button.is-success').click()
    cy.wrap(updatedSpy).should('have.been.called')
  })

  it('disables submit button if name is empty', () => {
    cy.mount(CategoryEditModal, {
      props: {
        isOpen: true,
        categoryData: { ...defaultCategory, name: '' },
      },
    })
    cy.get('button.is-success').should('be.disabled')
  })
})
