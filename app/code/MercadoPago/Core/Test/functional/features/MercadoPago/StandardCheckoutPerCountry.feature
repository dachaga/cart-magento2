@MercadoPago @reset_configs
Feature: Payment results in MercadoPago Standard Checkout

  @STANDARDPerCountry
  Scenario Outline: Generate order with sandbox mode
    When Setting merchant <country>
    And User "<user>" "<pass>" exists
    And I am logged in as "<user>" "<pass>"
    And I empty cart
    And I am on page "push-it-messenger-bag.html"
    And I press "#product-addtocart-button" element
    And I wait for "10" seconds
    And I am on page "checkout"
    And I wait for "10" seconds
    And I select shipping method "flatrate_flatrate"
    And I press "#shipping-method-buttons-container .button" element
    And I wait for "45" seconds
    And I select payment method "mercadopago_standard"
    And Setting Config "payment/mercadopago_standard/sandbox_mode" is "0"
    And I press "#iframe-submit" element
    And I wait for "10" seconds
    When I switch to the iframe "mercadopago_standard-iframe"
    And I wait for "15" seconds
    And I am logged in MP as "<user>" "<passmp>"
    And I fill the iframe shipping address fields "<country>"
    And I confirm shipping
    And I press "#next" input element
    And I wait for "10" seconds
    And I fill the iframe fields country <country>
    And I press "#next" input element
    And I switch to the site
    And I wait for "12" seconds
    Then I should be on "/mercadopago/success/page"
    And i revert configs

    Examples:
      | country | user                            | pass    | passmp     |
      | mlv     | test_user_58787749@testuser.com | magento | qatest850  |
      | mla     | test_user_58666377@testuser.com | magento | qatest3200 |
      | mlb     | test_user_98856744@testuser.com | magento | qatest1198 |