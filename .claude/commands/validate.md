Run the full validation gate in sequence. Stop immediately and report the failure if any gate exits non-zero.

```bash
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app composer format -- --test
docker compose exec app php artisan l5-swagger:generate
docker compose exec app php artisan l5-swagger:audit --fail-on-warnings
```

Report each gate as PASS or FAIL. On the first failure, show the relevant output and stop — do not continue to the next gate.
