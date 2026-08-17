<?php

namespace App\Models\Concerns;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFirm
{
    protected static function bootBelongsToFirm(): void
    {
        static::addGlobalScope('firm', function (Builder $query) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $column = $query->getModel()->getTable().'.firm_id';

            if (! $user->firm_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($column, $user->firm_id);
        });

        static::creating(function (Model $model) {
            if (! $model->getAttribute('firm_id') && auth()->user()?->firm_id) {
                $model->setAttribute('firm_id', auth()->user()->firm_id);
            }
        });
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }
}
