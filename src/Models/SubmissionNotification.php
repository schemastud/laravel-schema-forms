<?php

namespace Splicewire\SchemaForms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Splicewire\SchemaForms\Outbox\OutboxDelivery;

/**
 * A durable record of one submission-notification attempt: who it was for, where it
 * was headed, which notifier tried, and whether it landed. The row is written
 * `pending` before delivery and flipped to `sent` or `failed` after — so a host can
 * always see what was attempted and replay what failed. Not a queue: at-least-once
 * delivery is the outbox's job, ordering is not.
 *
 * Not final — a consuming app may extend it and point
 * `config('schema-forms.models.submission_notification')` at the subclass (Spatie
 * swappable-model pattern). Table name is read from config at runtime; `guarded = []`
 * because every write goes through {@see OutboxDelivery},
 * not user input.
 */
class SubmissionNotification extends Model
{
    use HasUuids;

    public const StatusPending = 'pending';

    public const StatusSent = 'sent';

    public const StatusFailed = 'failed';

    protected $guarded = [];

    protected $casts = [
        'intent' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('schema-forms.table_names.submission_notifications', 'submission_notifications');
    }

    /**
     * Entries still owed a successful delivery: never sent, and not yet past the
     * replay attempt ceiling.
     */
    public function scopeUnsent(Builder $query, ?int $maxAttempts = null): Builder
    {
        return $query
            ->whereIn('status', [self::StatusPending, self::StatusFailed])
            ->when($maxAttempts !== null, fn (Builder $q) => $q->where('attempts', '<', $maxAttempts));
    }
}
