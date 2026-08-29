<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\DtoSchemaDriftRule;
use App\Console\Commands\OpenApiAudit\Rules\IncompleteAnnotationsRule;
use App\Console\Commands\OpenApiAudit\Rules\MissingApiMiddlewareRule;
use App\Console\Commands\OpenApiAudit\Rules\PhantomPathsRule;
use App\Console\Commands\OpenApiAudit\Rules\UndocumentedRoutesRule;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class AuditOpenApiSpec extends Command
{
    protected $signature = 'l5-swagger:audit
        {--fail-on-warnings : Exit non-zero on warnings (incomplete annotations, missing api middleware)}
        {--spec-file= : Path to OpenAPI spec file (defaults to configured l5-swagger output)}
        {--dto-dir= : Directory to scan for DTOs (defaults to the app directory)}
        {--dto-namespace= : Base namespace matching --dto-dir (defaults to App\\)}';

    protected $description = <<<'TEXT'
        Audit OpenAPI spec — undocumented routes, phantom paths, DTO/schema drift,
        routes missing api middleware, incomplete annotations
        TEXT;

    public function handle(): int
    {
        $specFileOption = $this->option('spec-file');
        $specFile = is_string($specFileOption) ? $specFileOption : storage_path('api-docs/openapi.yaml');

        if (! file_exists($specFile)) {
            $this->error("Spec file not found: {$specFile}");
            $this->line('Run: php artisan l5-swagger:generate');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($specFile);

        $dtoDirOption = $this->option('dto-dir');
        $dtoDir = is_string($dtoDirOption) ? $dtoDirOption : app_path();

        $dtoNamespaceOption = $this->option('dto-namespace');
        $dtoNamespace = is_string($dtoNamespaceOption) ? $dtoNamespaceOption : 'App\\';

        $context = AuditContext::fromCommandOptions($spec, $dtoDir, $dtoNamespace);

        $results = $this->runRules($context);

        $this->renderFindings($results);

        $errorCount = $this->countFindings($results, AuditSeverity::Error);

        if ($errorCount > 0) {
            $this->newLine();
            $this->error("Audit failed: {$errorCount} error(s).");

            return self::FAILURE;
        }

        $warningCount = $this->countFindings($results, AuditSeverity::Warning);

        if ($warningCount > 0 && $this->option('fail-on-warnings')) {
            $this->newLine();
            $this->error("Audit failed: {$warningCount} warning(s) found (--fail-on-warnings).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Check', 'Result'], $this->summaryRows($context, $results));

        $warningNote = $warningCount > 0 ? " ({$warningCount} warning(s))" : '';
        $this->info("Audit passed{$warningNote}.");

        return self::SUCCESS;
    }

    /** @return AuditRule[] */
    private function rules(): array
    {
        return [
            new UndocumentedRoutesRule,
            new PhantomPathsRule,
            new IncompleteAnnotationsRule,
            new MissingApiMiddlewareRule,
            new DtoSchemaDriftRule,
        ];
    }

    /** @return array<int, array{rule: AuditRule, findings: array<int, AuditFinding>}> */
    private function runRules(AuditContext $context): array
    {
        return array_values(array_map(
            fn (AuditRule $rule): array => ['rule' => $rule, 'findings' => $rule->audit($context)],
            $this->rules()
        ));
    }

    /** @param array<int, array{rule: AuditRule, findings: array<int, AuditFinding>}> $results */
    private function countFindings(array $results, AuditSeverity $severity): int
    {
        $count = 0;

        foreach ($results as $result) {
            if ($result['rule']->severity() === $severity) {
                $count += count($result['findings']);
            }
        }

        return $count;
    }

    /** @param array<int, array{rule: AuditRule, findings: array<int, AuditFinding>}> $results */
    private function renderFindings(array $results): void
    {
        foreach ($results as $result) {
            $findings = $result['findings'];

            if (empty($findings)) {
                continue;
            }

            $rule = $result['rule'];
            $color = $rule->severity() === AuditSeverity::Error ? 'red' : 'yellow';
            $warningSuffix = $rule->severity() === AuditSeverity::Warning ? ' — warnings' : '';

            $this->newLine();
            $this->line("<fg={$color}>{$rule->name()} (".count($findings)."){$warningSuffix}:</>");

            foreach ($findings as $finding) {
                $this->line("  <fg={$color}>{$finding->subject}:</> ".implode(', ', $finding->issues));
            }
        }
    }

    /**
     * @param array<int, array{rule: AuditRule, findings: array<int, AuditFinding>}> $results
     * @return array<int, array{string, string}>
     */
    private function summaryRows(AuditContext $context, array $results): array
    {
        $rows = [
            ['Routes audited', (string) count($context->routes)],
            ['Spec paths', (string) count($context->specPaths)],
        ];

        foreach ($results as $result) {
            $rule = $result['rule'];
            $count = count($result['findings']);
            $label = $rule->severity() === AuditSeverity::Warning && $count > 0
                ? "{$count} warning(s)"
                : (string) $count;

            $rows[] = [$rule->name(), $label];
        }

        return $rows;
    }
}
