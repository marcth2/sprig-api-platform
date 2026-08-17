Audit the OpenAPI spec against the live routes and report findings.

1. Run the audit command:
   ```bash
   docker compose exec app php artisan l5-swagger:audit --fail-on-warnings
   ```

2. Interpret the output by category:

   - **Undocumented routes** — API routes that exist in Laravel but have no matching path in the spec. For each: identify the Action class responsible, show the route signature, and suggest the OA attribute block to add to `asController()`.

   - **Phantom paths** — paths in the spec that have no matching Laravel route. For each: confirm whether the route was removed or renamed, and recommend deleting the stale spec entry or updating the path.

   - **Incomplete annotations** — documented paths missing `operationId`, missing a `401` response on auth-required routes, or with empty response schemas. For each: show the current annotation and the specific field to add.

3. Prioritise undocumented routes (they hide API surface from consumers) over phantom paths (stale docs) over incomplete annotations (quality warnings).
