@registration
Feature: Patient and doctor registration
  Scenario: Patient registration succeeds with valid credentials
    When I send a "POST" request to "/api/register/patient" with JSON:
      """
      {
        "email": "patient-happy-path@example.test",
        "password": "StrongPassword!1"
      }
      """
    Then the response status code should be 201
    And the JSON response should contain:
      """
      {
        "id": "*"
      }
      """

  Scenario: Doctor registration succeeds with valid credentials
    When I send a "POST" request to "/api/register/doctor" with JSON:
      """
      {
        "email": "doctor-happy-path@example.test",
        "password": "StrongPassword!1",
        "specialization": "Cardiology"
      }
      """
    Then the response status code should be 201
    And the JSON response should contain:
      """
      {
        "id": "*"
      }
      """

  Scenario Outline: Registration rejects missing required fields
    When I send a "POST" request to "<endpoint>" with JSON:
      """
      <payload>
      """
    Then the response status code should be 422
    And the JSON response should contain:
      """
      {
        "detail": "<error>"
      }
      """

    Examples:
      | endpoint                | payload                                                                                           | error                                 |
      | /api/register/patient   | {"password":"StrongPassword!1"}                                                                   | Field \"email\" is required.          |
      | /api/register/patient   | {"email":"patient-missing-password@example.test"}                                                 | Field \"password\" is required.       |
      | /api/register/doctor    | {"password":"StrongPassword!1","specialization":"Cardiology"}                                     | Field \"email\" is required.          |
      | /api/register/doctor    | {"email":"doctor-missing-password@example.test","specialization":"Cardiology"}                    | Field \"password\" is required.       |
      | /api/register/doctor    | {"email":"doctor-missing-specialization@example.test","password":"StrongPassword!1"}              | Field \"specialization\" is required. |

  Scenario Outline: Registration rejects invalid field formats
    When I send a "POST" request to "<endpoint>" with JSON:
      """
      <payload>
      """
    Then the response status code should be 422
    And the JSON response should contain:
      """
      {
        "detail": "<error>"
      }
      """

    Examples:
      | endpoint              | payload                                                                                                         | error                                                           |
      | /api/register/patient | {"email":"not-an-email","password":"StrongPassword!1"}                                                          | Field \"email\" must contain a valid email address.             |
      | /api/register/patient | {"email":"patient-weak-password@example.test","password":"weak"}                                                | Field \"password\" does not meet minimum security requirements. |
      | /api/register/doctor  | {"email":"still-not-an-email","password":"StrongPassword!1","specialization":"Cardiology"}                      | Field \"email\" must contain a valid email address.             |
      | /api/register/doctor  | {"email":"doctor-weak-password@example.test","password":"weak","specialization":"Cardiology"}                   | Field \"password\" does not meet minimum security requirements. |
      | /api/register/doctor  | {"email":"doctor-invalid-specialization@example.test","password":"StrongPassword!1","specialization":"Dentist"} | Field \"specialization\" contains an unsupported value.         |

  Scenario: Patient registration rejects malformed JSON
    When I send a "POST" request to "/api/register/patient" with raw body:
      """
      {"email":"patient-malformed@example.test","password":"StrongPassword!1"
      """
    Then the response status code should be 400
    And the JSON response should contain:
      """
      {
        "detail": "Request body must contain valid JSON."
      }
      """

  Scenario: Doctor registration rejects malformed JSON
    When I send a "POST" request to "/api/register/doctor" with raw body:
      """
      {"email":"doctor-malformed@example.test","password":"StrongPassword!1","specialization":"Cardiology"
      """
    Then the response status code should be 400
    And the JSON response should contain:
      """
      {
        "detail": "Request body must contain valid JSON."
      }
      """

  Scenario: Patient registration returns conflict for duplicate email
    When I send a "POST" request to "/api/register/patient" with JSON:
      """
      {
        "email": "patient-duplicate@example.test",
        "password": "StrongPassword!1"
      }
      """
    And I send a "POST" request to "/api/register/patient" with JSON:
      """
      {
        "email": "patient-duplicate@example.test",
        "password": "StrongPassword!1"
      }
      """
    Then the response status code should be 409
    And the JSON response should contain:
      """
      {
        "detail": "Account could not be created due to conflict."
      }
      """

  Scenario: Doctor registration returns conflict for duplicate email
    When I send a "POST" request to "/api/register/doctor" with JSON:
      """
      {
        "email": "doctor-duplicate@example.test",
        "password": "StrongPassword!1",
        "specialization": "Dermatology"
      }
      """
    And I send a "POST" request to "/api/register/doctor" with JSON:
      """
      {
        "email": "doctor-duplicate@example.test",
        "password": "StrongPassword!1",
        "specialization": "Dermatology"
      }
      """
    Then the response status code should be 409
    And the JSON response should contain:
      """
      {
        "detail": "Account could not be created due to conflict."
      }
      """

  Scenario Outline: Registration rejects unsupported methods
    When I send a "<method>" request to "<endpoint>"
    Then the response status code should be 405

    Examples:
      | method | endpoint              |
      | GET    | /api/register/patient |
      | GET    | /api/register/doctor  |
