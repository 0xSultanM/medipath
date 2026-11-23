# Contributing to MediPath

Thanks for considering a contribution. This project is an educational clinic platform; please keep changes focused, tested and safe.

## Before you start

- Read `README.md` and set up the app locally.
- For larger features, open an issue first so the approach can be discussed.
- Do not include real patient data, production credentials or `.env` files.

## Development workflow

1. Fork the repository.
2. Create a branch:

   ```bash
   git checkout -b feature/short-description
   ```

3. Make the change.
4. Add or update tests.
5. Run the checks:

   ```bash
   cd medipath
   php artisan test --compact
   npm run typecheck
   npm run build
   ```

6. Commit with a clear message:

   ```bash
   git add .
   git commit -m "feat: add pharmacy restock validation"
   ```

7. Push and open a pull request.

## Coding guidelines

- Follow the existing Laravel/React/Inertia patterns and folder structure.
- Keep clinical validation server-authoritative. Never trust client-side quantity, price or safety state.
- Show money only through the existing `CurrencyFormatter` / `Money` contract.
- Keep database changes in migrations; add indexes when adding list filters.
- Preserve the append-only audit and inventory ledger behaviour.
- Use the existing design tokens and components; do not introduce hardcoded colours.
- Keep TypeScript strict-clean (`npm run typecheck`).

## Pull request checklist

- [ ] The change is covered by a Pest test (and Dusk if it is a screen-level change).
- [ ] `php artisan test --compact` passes.
- [ ] `npm run typecheck` and `npm run build` pass.
- [ ] No secrets, `.env` files, `vendor/`, `node_modules/` or private uploads are committed.
- [ ] Documentation is updated if behaviour or setup changed.

## Reporting bugs

Open an issue with:

- expected behaviour,
- actual behaviour,
- steps to reproduce,
- PHP/Node/MySQL versions,
- relevant logs (remove secrets and patient data).

## Code of conduct

By participating, you agree to follow `CODE_OF_CONDUCT.md`.