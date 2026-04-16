import CategoryCreateModal from '../../../src/views/CategoryCreateModal.vue'

describe('CategoryCreateModal.vue', () => {
  const defaultCategory = {
    name: 'Test Category',
    description: 'Test Description',
    printer_type: '',
  }

  it('renders modal when isOpen is true', () => {
    cy.mount(CategoryCreateModal, {
      props: {
        isOpen: true,
      },
    })
    cy.get('.modal.is-active').should('exist')
    cy.get('.modal-card-title').contains('Ajouter une catégorie')
  })

  it('does not render modal when isOpen is false', () => {
    cy.mount(CategoryCreateModal, {
      props: {
        isOpen: false,
      },
    })
    cy.get('.modal.is-active').should('not.exist')
  })

  it('binds form inputs to category data', () => {
    cy.mount(CategoryCreateModal, {
      props: {
        isOpen: true,
      },
    })
    cy.get('input[type="text"]')
      .type(defaultCategory.name)
      .should('have.value', defaultCategory.name)
    cy.get('textarea')
      .type(defaultCategory.description)
      .should('have.value', defaultCategory.description)
  })

  it('emits added event with form data on submit', () => {
    const addedSpy = cy.spy()
    cy.mount(CategoryCreateModal, {
      props: {
        isOpen: true,
        onAdded: addedSpy,
      },
    })
    cy.get('input[type="text"]').type(defaultCategory.name)
    cy.get('button.is-success').click()
    cy.wrap(addedSpy).should('have.been.called')
  })

  it('disables submit button if name is empty', () => {
    cy.mount(CategoryCreateModal, {
      props: {
        isOpen: true,
      },
    })
    cy.get('button.is-success').should('be.disabled')
  })
})
