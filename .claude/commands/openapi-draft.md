Draft OpenAPI PHP 8 attribute blocks for a Laravel Action file.

Usage: `/openapi-draft <path-to-action-file>`

Steps:

1. Read the specified Action file.
2. Identify the `asController()` method:
   - Extract the HTTP method and route path from the registered route (check `routes/api/` files).
   - Extract request parameters (route bindings, query params, request body).
   - Extract the return type and response shape.
3. Read any existing `#[OA\...]` attributes on the file for style reference.
4. Use `app/HealthCheck/Actions/CheckServiceHealth.php` as the canonical style reference:
   - Namespace: `use OpenApi\Attributes as OA;`
   - Each HTTP verb on `asController()` gets its own `#[OA\Get]` / `#[OA\Post]` etc.
   - Use `ref:` to reference schemas defined by `#[OA\Schema]` on the owning DTO class (see `app/OpenApi/CLAUDE.md`) — do not create standalone schema-holder classes in `app/OpenApi/`.
   - Include `operationId`, `summary`, `tags`, `security` (if auth:sanctum), `parameters`, and `responses` (200, 401 where applicable, 404 for `{id}` routes, 422 for POST/PUT).
5. Output the complete attribute block(s) ready to paste above `asController()`.

Do not modify any files — output only.
