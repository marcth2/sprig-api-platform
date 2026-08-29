# Console Directory

## Purpose

Artisan commands. Most commands here are simple (`CreateUserToken.php`). `l5-swagger:audit` (`AuditOpenApiSpec.php`) is the exception — it runs five independent audit rules and has enough internal structure to need its own documented extension point, in `OpenApiAudit/`.

## `AuditRule` contract

```php
interface AuditRule
{
    public function name(): string;
    public function severity(): AuditSeverity;      // Error | Warning
    public function audit(AuditContext $context): array; // array<int, AuditFinding>
}
```

`AuditContext` (`OpenApiAudit/AuditContext.php`) is built once per command run — parsed OpenAPI spec paths, all routes, api-middleware-filtered routes, and reflected `#[OA\Schema]` DTO classes — and passed to every rule, including DTO/schema drift (which does its own `#[OA\Property]` reflection against the already-discovered classes, but doesn't do the filesystem scan itself). `AuditFinding` (`OpenApiAudit/AuditFinding.php`) is the shared result shape: `{subject: string, issues: string[]}`.

`AuditOpenApiSpec::handle()` builds the `AuditContext`, runs each rule, renders findings (one shared renderer, no bespoke per-rule formatting), and fails on any `Error`-severity finding always, or any `Warning`-severity finding only with `--fail-on-warnings`.

## How to add a new audit rule

1. Create `OpenApiAudit/Rules/YourRule.php` implementing `AuditRule`.
2. If the rule needs data `AuditContext` doesn't already expose, add it there — not as ad-hoc work inside the rule itself. Rules should only read from `AuditContext`, never do their own file/route/reflection scanning.
3. Register the class in `AuditOpenApiSpec::rules()`. No config file, no `ServiceProvider` — this is a single command, not a domain.
4. Add a unit test in `tests/Unit/Console/OpenApiAudit/Rules/YourRuleTest.php` constructing an `AuditContext` fixture directly and asserting on the returned `AuditFinding[]` — no `artisan()` call needed.

## History

Extracted from a single 538-line command where all five rules were private methods, only reachable through a full CLI invocation in tests. Decided in [#186](https://github.com/marcth2/sprig-api-platform/issues/186), implemented in [#187](https://github.com/marcth2/sprig-api-platform/issues/187).
