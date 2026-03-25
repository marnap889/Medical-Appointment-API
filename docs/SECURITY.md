# Security posture

## Security goals

- minimize accidental data exposure
- minimize authentication-side information leakage
- avoid storing sensitive data in AI logs
- keep destructive commands approved
- prefer deterministic, auditable quality gates

## OWASP-minded controls

- explicit validation on request DTOs
- thin controllers
- no secrets committed to the repo
- structured separation of domain and infrastructure
- database access through clearly owned boundaries
- CORS is intentionally documented and limited to an API path
- password hashes only (no plain-text credential storage)
- password policy is enforced at the HTTP boundary before registration is accepted
- patient and doctor login return a bearer JWT on valid credentials
- role-based access split (`ROLE_PATIENT`, `ROLE_DOCTOR`)
- endpoint data scope limited to one patient or one doctor context
- approvals enabled for main Codex interactive sessions
- subagents run in non-interactive sandboxed mode where possible
- AI workflow artifacts isolated under `ai-orchestration/runtime/`

## Authentication baseline

- authentication is stateless and uses the `Authorization: Bearer <token>` header
- the API issues JWTs for both patient and doctor login flows
- JWTs are signed symmetrically and validated for signature integrity and expected issuer
- issued tokens include the user identifier as the JWT subject and the resolved Symfony roles as a claim
- backend authorization uses the verified JWT `sub` only as a lookup key, then reloads the user and current roles from the database
- the roles claim is informational only and is not trusted for backend security assertions
- the default token TTL is one hour
- public access is limited to health, registration, and login endpoints; the main firewall protects the remaining API surface

## Public contract hardening

- successful registration responses return only `{ "id": "<uuid>" }`
- registration responses do not expose roles, activation flags, or doctor specialization values
- `DoctorSpecialization` enum values use PascalCase everywhere, for example `Cardiology`

## Password handling and policy

- runtime password storage is hash-only through Symfony password hashers
- plain-text passwords are accepted only at registration/login request time and are not persisted
- registration requires a password with:
  - minimum length of 12 characters
  - at least one uppercase Unicode letter
  - at least one lowercase Unicode letter
  - at least one digit
  - at least one non-letter, non-digit character
- weak passwords fail with `422 Unprocessable Entity`

## Neutral error hardening

- login failures return a neutral `401 Unauthorized` response with `Invalid authentication credentials.`
- the login flow does not reveal whether the email exists or whether the password was wrong
- bearer token authentication failures return an empty `401 Unauthorized` response instead of token parsing details
- unexpected server failures are normalized to `500 Internal Server Error` with `An unexpected error occurred.`
- these defaults reduce account enumeration and token-debug leakage through public API responses

## Future enhancements

- rate limiting
- JWT key rotation and revocation strategy
- API idempotency keys for booking
- optimistic locking for slot contention
- audit trail for appointment lifecycle
- PII masking strategy in production logs
