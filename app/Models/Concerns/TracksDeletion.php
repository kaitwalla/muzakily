<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\DeletedItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for tracking deletions in the deleted_items table.
 *
 * Add this trait to models that need deletion tracking for incremental sync.
 * The trait hooks into the "deleting" event to record deletions before the model is removed.
 */
trait TracksDeletion
{
    /**
     * Boot the trait.
     */
    public static function bootTracksDeletion(): void
    {
        static::deleting(function (Model $model): void {
            // Type assertion: this closure is only called from models using this trait
            if (method_exists($model, 'recordDeletion')) {
                $model->recordDeletion();
            }
        });
    }

    /**
     * Record this model's deletion in the deleted_items table.
     */
    protected function recordDeletion(): void
    {
        $type = $this->getDeletableType();
        $id = $this->getDeletableId();
        $userId = $this->getDeletableUserId();

        DeletedItem::recordDeletion($type, $id, $userId);
    }

    /**
     * Get the deletable type name for this model.
     */
    protected function getDeletableType(): string
    {
        // Convert class name to lowercase singular (e.g., Song -> song)
        $className = class_basename(static::class);

        return strtolower($className);
    }

    /**
     * Get the deletable ID for this model.
     * Defaults to the primary key value, but can be overridden for models with UUIDs.
     */
    protected function getDeletableId(): string
    {
        // Use uuid if available (for Album, Artist), otherwise use primary key
        return (string) ($this->getAttribute('uuid') ?? $this->getKey());
    }

    /**
     * Get the user ID associated with this deletable item.
     * Override in models that are user-scoped (e.g., Playlist).
     */
    protected function getDeletableUserId(): ?int
    {
        return null;
    }
}
