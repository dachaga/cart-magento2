@MercadoPagoStandard
Feature: A customer should be able to do a checkout with MercadoPago

  Background:
    Given Setting merchant "mla"
    And User "test_user_58666377@testuser.com" "magento" exists
    And I am logged in as "test_user_58666377@testuser.com" "magento"
    And I empty cart
    And I am on page "push-it-messenger-bag.html"
    And I press "#product-addtocart-button" element

  @viewStandard
  Scenario: See MercadoPago standard option as a payment method
    And I configure mercadopago standard
    And I am on page "checkout"
    And I wait for "6" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "50" seconds
    Then I should see MercadoPago Standard available

  @Availability @ClientId
  Scenario: Not See MercadoPago option as a payment method when is not client id
    When Setting Config "payment/mercadopago_standard/client_id" is "0"
    And I am on page "checkout"
    And I wait for "6" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "50" seconds
    Then I should not see MercadoPago Standard available
    And i revert configs

  @Availability @ClientSecret
  Scenario: Not See MercadoPago option as a payment method when is not available client secret
    When Setting Config "payment/mercadopago_standard/client_secret" is "0"
    And I am on page "checkout"
    And I wait for "10" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "50" seconds
    Then I should not see MercadoPago Standard available
    And i revert configs

  @checkoutSuccess
  Scenario: Generate order with standard checkout
    When I am on page "checkout"
    And I wait for "10" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "60" seconds
    And I select payment method "mercadopago_standard"
    And I press "#iframe-submit" element
    And I wait for "5" seconds
    When I switch to the iframe "mercadopago_standard-iframe"
    And I wait for "15" seconds
    And I am logged in MP as "test_user_58666377@testuser.com" "qatest3200"
    And I press "#next" input element
    And I fill the iframe fields

    When I press "#next" input element
    And I switch to the site
    Then I should be on "/success/page"

  @checkoutPrice
  Scenario: Check total displayed in iframe
    When I am on page "checkout"
    And I fill the shipping address
    And I wait for "6" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "50" seconds
    And I select payment method "mercadopago_standard"
    And I press "#iframe-submit" element
    And I wait for "5" seconds
    When I switch to the iframe "mercadopago_standard-iframe"
    And I am logged in MP as "test_user_58666377@testuser.com" "qatest3200"
    And I press "#next" input element
    Then I should see html "$ 50"



