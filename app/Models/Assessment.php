<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'resident_id',
        'branch_id',
        'assessor_id',
        'assessment_type',
        'assessment_date',
        'status',
        'notes',
        'scores',
        'recommendations',
        'completed_at',
        'reviewed_at',
        'approved_at',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'scores' => 'array',
        'recommendations' => 'array',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AssessmentSection::class);
    }

    // Scopes
    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('assessment_type', $type);
    }

    // Accessors
    public function getCompletionPercentageAttribute()
    {
        // Calculate based on answered questions for more accurate partial progress
        $totalQuestions = $this->sections()
            ->with('questions')
            ->get()
            ->flatMap(fn($section) => $section->questions)
            ->count();
            
        if ($totalQuestions === 0) {
            return 0;
        }
        
        $answeredQuestions = $this->sections()
            ->with('questions')
            ->get()
            ->flatMap(fn($section) => $section->questions)
            ->whereNotNull('response_value')
            ->where('response_value', '!=', '')
            ->count();
        
        return round(($answeredQuestions / $totalQuestions) * 100, 2);
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'approved';
    }
}
