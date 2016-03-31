@MercadoPago
Feature: A customer should be able to do a checkout with MercadoPago applying a coupon discount

  Background:
    Given Setting merchant "mla"
    And User "test_user_58666377@testuser.com" "magento" exists
    And Setting Config "payment/mercadopago_customticket/coupon_mercadopago" is "1"
    And I am logged in as "test_user_58666377@testuser.com" "magento"
    And I empty cart
    And I am on page "push-it-messenger-bag.html"
    And I press "#product-addtocart-button" element
    And I am on page "checkout"
    And I wait for "6" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "50" seconds


  @customTicketFormDiscountReview
  Scenario: Seeing subtotal discount in review with custom ticket checkout
    And I select payment method "mercadopago_customticket"
    And I fill text field "#input-coupon-discount" in form "#payment_form_mercadopago_customticket" with "TESTEMP"
    And I press "#payment_form_mercadopago_customticket .mercadopago-coupon-action-apply" input element
    And I wait for "10" seconds
    And I select ticket "bapropagos"
    And I press "#ticket-submit" element
    And I wait for "10" seconds
    And I should see "Will be approved"
    And I am on page "sales/order/history/"
    And I press "td.actions a" element
    Then I should see "Discount Mercado Pago"
