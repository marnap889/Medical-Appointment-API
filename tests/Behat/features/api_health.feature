Feature: API health check
  Scenario: The API health endpoint reports a healthy status
    When I send a "GET" request to "/api/health"
    Then the response status code should be 200
    And the response should be a valid health check payload
