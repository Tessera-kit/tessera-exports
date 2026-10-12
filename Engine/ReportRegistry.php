<?php

namespace App\Modules\Exports\Engine;

use InvalidArgumentException;
use Tessera\Engine\Exports\ReportDefinition;
use Tessera\Engine\ModuleRegistry;

/**
 * The reports that can be exported: whatever the enabled modules list in their exports().
 */
class ReportRegistry
{
    /**
     * @var array<string, ReportDefinition>|null
     */
    private ?array $instances = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return array<string, ReportDefinition>
     */
    public function all(): array
    {
        return $this->instances ??= collect($this->modules->exports())
            ->map(fn (string $class): ReportDefinition => app($class))
            ->keyBy(fn (ReportDefinition $report): string => $report->key())
            ->all();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): ReportDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown report [{$key}].");
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
