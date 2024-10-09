class Order {
    clrcookies() {
        cy.clearCookies()
    }
    visit() {
        cy.fixture('config').then((url) => {
            cy.visit(url.shopURL)
        })
    }


    addProduct() {
        cy.get('.nav-item-a515ae260223466f8e37471d279e6406 > .main-navigation-link-text > span').click()
        cy.get(':nth-child(1) > .card > .card-body > .product-info > .product-action > .buy-widget > .d-grid > .btn').click()
        cy.get('.offcanvas-cart-actions > :nth-child(2) > .btn').click()
    }

    cc(CC_TERMINAL_NAME) {
        cy.contains(CC_TERMINAL_NAME).click({ force: true })
        cy.get('#confirmFormSubmit').click()

        cy.origin('https://testgateway.altapaysecure.com', () => {
            cy.get('#creditCardNumberInput').type('4222222222222222')
            cy.get('#cvcInput').type('123')
            cy.get('#cardholderNameInput').type('smith')
            cy.get('#pensioCreditCardPaymentSubmitButton').click().wait(3000)

        })
        cy.get('body').should('contain', 'Order confirmation email has been sent').wait(3000)
    }

    klarna(KLARNA_DKK_TERMINAL_NAME) {
        cy.contains(KLARNA_DKK_TERMINAL_NAME).click({ force: true })
        cy.get('#confirmFormSubmit').click()
        cy.wait(3000)
        cy.get('#radio_pay_later').click().wait(8000)
        cy.get('[id=submitbutton]').click().wait(5000)
        cy.wait(5000)

    }

    visitAdminOrder() {
        cy.fixture('config').then((conf) => {
            cy.clearCookies()
            cy.visit(conf.adminURL)
            cy.get('#sw-field--username').type(conf.adminUsername)
            cy.get('#sw-field--password').type(conf.adminPass)
            cy.get('.sw-button').click().wait(3000)
            cy.get('.sw-order > span.sw-admin-menu__navigation-link').click()
            cy.get('.sw-admin-menu_flyout-holder > .sw-admin-menu__navigation-list-item > .sw-admin-menu__navigation-link').click()
            cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--orderNumber > .sw-data-grid__cell-content > a').click()
            cy.get('.sw-order-detail__tabs-tab-altaPay').click()
        })

    }

    capture() {
        cy.get(':nth-child(2) > .sw-container > :nth-child(1)').click()
        cy.get('.sw-container > :nth-child(10)').should('have.text', '19.99 DKK');



    }

    refund() {
        cy.get(':nth-child(2) > .sw-container > :nth-child(2)').click()
        cy.get('.sw-container > :nth-child(12)').should('have.text', '19.99 DKK');

    }

    release() {
        cy.get(':nth-child(2) > .sw-container > :nth-child(3)').click()
        cy.get('.sw-container > :nth-child(6)').should('have.text', 'released');
    }

    fillCheckoutInfo() {
        cy.get('#personalFirstName').type('Test')
        cy.get('#personalLastName').type('Person')
        cy.get('#personalMail').type('demo@example.com')
        cy.get('#billingAddressAddressStreet').type('Sæffleberggate 56,1 mf')
        cy.get('#billingAddressAddressZipcode').type('6800')
        cy.get('#billingAddressAddressCity').type('Varde')
        cy.get('.register-submit > .btn').click().wait(3000)
        cy.get('#tos').click()
    }
}

export default Order
