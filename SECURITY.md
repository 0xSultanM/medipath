# Security policy

MediPath is an educational project, not a certified clinical system. Do not deploy it with real patient data without an independent security, privacy and clinical review.

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Contact the maintainers privately, for example through the repository owner’s GitHub profile, and include:

- a description of the issue,
- affected routes or components,
- reproduction steps,
- potential impact,
- any suggested fix.

Allow reasonable time for a fix before public disclosure.

## Secrets and sensitive data

Never commit:

- `.env`, `.env.*` (except the provided examples),
- API keys, database passwords, OAuth secrets,
- real patient records, names, phone numbers or clinical notes,
- `storage/app/private` uploads or database dumps,
- production backups,
- `graphify-out/.graphify_python` or `.graphify_root` (they contain local absolute paths).

The demo accounts and seeded data are fictional and intended for local development only.

## Deployment notes

If you adapt this project for a public instance:

- Replace the seeded demo accounts and password.
- Use HTTPS and secure session cookies.
- Keep `APP_DEBUG=false` in production.
- Configure a real mail transport and queue worker.
- Rate-limit authentication, chatbot and public search routes.
- Restrict private uploads and PDF access to the owning patient/role.
- Back up the database and audit logs; make backups private.
- Review the AI provider configuration and never send more patient context than necessary.

## Supported versions

Security fixes target the current `main` branch. This project does not maintain older branches.