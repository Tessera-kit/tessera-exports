<?php

namespace App\Modules\Exports\Models;

use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportStatus;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportFile extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'report',
        'format',
        'locale',
        'filters',
        'status',
        'estimated_rows',
        'rows',
        'path',
        'filename',
        'size',
        'error',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'estimated_rows' => 'integer',
            'rows' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready
            && $this->path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
