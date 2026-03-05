<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'latitude',
        'longitude',
        'category_id',
        'reporter_ip_hash',
        'reporter_browser_id',
        'confirmations_count',
        'clear_votes_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'confirmations_count' => 'integer',
            'clear_votes_count' => 'integer',
            'status' => ReportStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ReportVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ReportVote::class);
    }

    public function scopeVisibleOnMap(Builder $query): Builder
    {
        return $query->where('status', '!=', ReportStatus::Resolved->value);
    }

    public function belongsToReporter(string $ipHash, string $browserId): bool
    {
        if ($this->reporter_browser_id && hash_equals($this->reporter_browser_id, $browserId)) {
            return true;
        }

        if ($this->reporter_ip_hash && hash_equals($this->reporter_ip_hash, $ipHash)) {
            return true;
        }

        return false;
    }
}
