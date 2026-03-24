# Security posture

## Security goals

- minimize accidental data exposure
- avoid storing sensitive data in AI logs
- keep destructive commands approved
- prefer deterministic, auditable quality gates

## OWASP-minded controls

- explicit validation on request DTOs
- thin controllers
- no secrets committed to the repo
- structured separation of domain and infrastructure
- database access through clearly owned boundaries
- CORS intentionally documented and limited to API path
- approvals enabled for main Codex interactive sessions
- subagents run in non-interactive sandboxed mode where possible
- AI workflow artifacts isolated under `ai-orchestration/runtime/`

## Future enhancements

- authentication and authorization
- rate limiting
- API idempotency keys for booking
- optimistic locking for slot contention
- audit trail for appointment lifecycle
- PII masking strategy in production logs
