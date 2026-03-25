Feature: API login
  Scenario: Login succeeds with valid credentials
    When I send a "POST" request to "/api/login" with JSON:
      """
      {
        "email": "login.fixture@example.test",
        "password": "StrongPassword!1"
      }
      """
    Then the response status code should be 200
    And the JSON response should have exactly the keys "token"
    And the JSON response should contain:
      """
      {
        "token": "*"
      }
      """

  Scenario Outline: Login rejects missing required fields
    When I send a "POST" request to "/api/login" with JSON:
      """
      <payload>
      """
    Then the response status code should be 422
    And the JSON response should have exactly the keys "detail, status, title, type"
    And the JSON response should contain:
      """
      {
        "type": "about:blank",
        "title": "Unprocessable Entity",
        "status": 422,
        "detail": "<detail>"
      }
      """

    Examples:
      | payload                                     | detail                           |
      | {"password":"StrongPassword!1"}             | Field \"email\" is required.     |
      | {"email":"login.fixture@example.test"}      | Field \"password\" is required.  |

  Scenario: Login rejects invalid email format
    When I send a "POST" request to "/api/login" with JSON:
      """
      {
        "email": "not-an-email",
        "password": "StrongPassword!1"
      }
      """
    Then the response status code should be 422
    And the JSON response should have exactly the keys "detail, status, title, type"
    And the JSON response should contain:
      """
      {
        "type": "about:blank",
        "title": "Unprocessable Entity",
        "status": 422,
        "detail": "Field \"email\" must contain a valid email address."
      }
      """

  Scenario: Login rejects malformed JSON
    When I send a "POST" request to "/api/login" with raw body:
      """
      {"email":"login.fixture@example.test","password":"StrongPassword!1"
      """
    Then the response status code should be 400
    And the JSON response should have exactly the keys "detail, status, title, type"
    And the JSON response should contain:
      """
      {
        "type": "about:blank",
        "title": "Bad Request",
        "status": 400,
        "detail": "Request body must contain valid JSON."
      }
      """

  Scenario: Login rejects invalid credentials
    When I send a "POST" request to "/api/login" with JSON:
      """
      {
        "email": "login.fixture@example.test",
        "password": "WrongPassword!1"
      }
      """
    Then the response status code should be 401
    And the JSON response should have exactly the keys "detail, status, title, type"
    And the JSON response should contain:
      """
      {
        "type": "about:blank",
        "title": "Unauthorized",
        "status": 401,
        "detail": "Invalid authentication credentials."
      }
      """

  Scenario Outline: Login rejects unsupported methods
    When I send a "<method>" request to "/api/login"
    Then the response status code should be 405

    Examples:
      | method |
      | GET    |
