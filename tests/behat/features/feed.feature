Feature: Dynamic Feed
    In order to parse Doofinder feed
    As a Doofinder
    I need to be able to dynamically generate feed

    Scenario: Sample feed
        Given I am on "/doofinder/feed"
        Then the response status code should be 200
        Then the response header 'Content-Type' should be 'application/xml; charset="utf-8"'
        Then the response should equal test feed
