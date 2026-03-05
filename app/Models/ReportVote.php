<?php

namespace App\Models;

use App\Enums\ReportVoteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVote extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'vote_type',
        'ip_hash',
        'browser_id',
    ];

    protected function casts(): array
    {
        return [
            'vote_type' => ReportVoteType::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
