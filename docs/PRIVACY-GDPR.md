# Privacy and GDPR notes

## Personal data in scope

This system processes personal data such as:
- patient name
- patient email
- appointment metadata

## Design principles

- data minimization
- purpose limitation
- storage limitation
- least-privilege operational access
- avoid placing personal data in AI workflow logs

## Logging rule

AI evidence logs should capture:
- prompts
- task summaries
- changed files
- approvals
- tool execution summaries

They should avoid:
- raw production secrets
- unnecessary personal data
- medical notes or diagnosis content

Operational defaults for this repository:
- AI runtime artifacts are isolated in `ai-orchestration/runtime/`
- Codex session mirroring is `CODEX_SESSION_MIRROR_MODE=repo-only` by default (external session directories are skipped unless explicitly enabled)
- Mirrored session scope is bounded via `CODEX_SESSION_MIRROR_MAX_FILES` to reduce uncontrolled log growth

## Future production requirements

- retention policy
- consent/legal basis analysis
- subject rights workflow
- encryption/key management policy
- formal RoPA / DPA documentation
