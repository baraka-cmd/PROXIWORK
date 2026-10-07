<?php

declare(strict_types=1);

namespace AppModels;

use AppEnumsReportPriority;
use AppEnumsReportStatus;
use IlluminateDatabaseEloquentFactoriesHasFactory;
use IlluminateDatabaseEloquentModel;
use IlluminateDatabaseEloquentRelationsBelongsTo;
use IlluminateDatabaseEloquentRelationsHasMany;
use IlluminateDatabaseEloquentRelationsMorphTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id','target_type','target_id','reason_code','description',
        'status','priority','assigned_to','resolved_by','resolved_at','resolution_note',
    ];

    protected function casts(): array
    {
        return ['status'=>ReportStatus::class,'priority'=>ReportPriority::class,'resolved_at'=>'datetime'];
    }

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function target(): MorphTo { return $this->morphTo(); }
    public function moderationActions(): HasMany { return $this->hasMany(ModerationAction::class); }
}
