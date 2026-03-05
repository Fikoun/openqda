<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A Category groups codes and/or other categories.
 * It can represent either a "category" or a "theme".
 */
class Category extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'description',
        'color',
        'type',
        'project_id',
        'creating_user_id',
        'parent_id',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Get the project this category belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created this category.
     */
    public function creatingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creating_user_id');
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get child categories recursively.
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Get the codes associated with this category.
     */
    public function codes(): BelongsToMany
    {
        return $this->belongsToMany(Code::class, 'category_code')
            ->withTimestamps();
    }
}
