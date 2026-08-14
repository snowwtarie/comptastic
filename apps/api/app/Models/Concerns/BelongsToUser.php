<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ForCurrentUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes every query to the authenticated user's own rows and stamps
 * user_id automatically on create. Because implicit route-model binding
 * (e.g. `Debt $debt` in a controller method) runs through the model's
 * default query, a record belonging to another user resolves to a 404,
 * not a 403 — this is intentional (see docs/backend-spec.md §4).
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new ForCurrentUser);

        static::creating(function (Model $model) {
            if (auth()->check() && ! $model->user_id) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
