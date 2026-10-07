/// <reference types= "cypress" /> 
import Order from "../commons/utils.cy"

it('CC Capture & Refund', function () {
    
    const ord = new Order()
    ord.clrcookies()
    ord.visit()

    ord.addProduct()
    ord.fillCheckoutInfo()
    cy.fixture('config').then((conf) => {
        if (conf.CC_TERMINAL_NAME != "") {
            cy.get('body').then(($a) => {
                if ($a.find("label:contains('" + conf.CC_TERMINAL_NAME + "')").length) {
                    ord.cc(conf.CC_TERMINAL_NAME)
                    ord.visitAdminOrder()
                    ord.capture()
                    ord.refund()
                } else {
                    cy.log(conf.CC_TERMINAL_NAME + ' not found in page')
                    this.skip()
                }
            })
        }else {
            cy.log('CC_TERMINAL_NAME skipped')
            this.skip()
        }
    })
})

it('CC Release Payment', function () {
    const ord = new Order()
    ord.clrcookies()
    ord.visit()

    ord.addProduct()
    ord.fillCheckoutInfo()
    cy.fixture('config').then((conf) => {
        if (conf.CC_TERMINAL_NAME != "") {
            cy.get('body').then(($a) => {
                if ($a.find("label:contains('" + conf.CC_TERMINAL_NAME + "')").length) {
                    ord.cc(conf.CC_TERMINAL_NAME)
                    ord.visitAdminOrder()
                    ord.release()
                } else {
                    cy.log(conf.CC_TERMINAL_NAME + ' not found in page')
                    this.skip()
                }
            })
        }else {
            cy.log('CC_TERMINAL_NAME skipped')
            this.skip()
        }
    })
})

it('Klarna Open Form', function () {
   
    const ord = new Order()
    ord.clrcookies()
    ord.visit()

    ord.addProduct()
    ord.fillCheckoutInfo()
    cy.fixture('config').then((conf) => {
        if (conf.KLARNA_DKK_TERMINAL_NAME != "") {
            cy.get('body').then(($a) => {
                if ($a.find("label:contains('" + conf.KLARNA_DKK_TERMINAL_NAME + "')").length) {
                    ord.klarna(conf.KLARNA_DKK_TERMINAL_NAME)
                } else {
                    cy.log(conf.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                    this.skip()
                }
            })
        }else {
            cy.log('CC_TERMINAL_NAME skipped')
            this.skip()
        }
    })
})