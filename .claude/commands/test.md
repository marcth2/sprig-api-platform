Run the test suite. Behaviour depends on the arguments provided:

- **No arguments (default):** Run tests filtered to PHP files changed since the last commit.
  ```bash
  git diff --name-only HEAD -- '*.php' | grep '^tests/' | head -1
  docker compose exec app php artisan test <filtered-test-files>
  ```
  If no changed test files are found, fall back to running all tests.

- **`--all`:** Run the full test suite.
  ```bash
  docker compose exec app php artisan test
  ```

- **`--coverage`:** Run the full test suite with coverage report.
  ```bash
  docker compose exec app php artisan test --coverage
  ```

Always report the number of tests passed, failed, and total duration.
