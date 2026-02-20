<?php

declare(strict_types=1);

namespace App\Services\Playlist;

use App\Enums\SmartPlaylistField;
use App\Enums\SmartPlaylistOperator;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SmartPlaylistEvaluator
{
    /**
     * Evaluate a smart playlist and return matching songs.
     *
     * If the playlist is materialized and up-to-date, returns songs from the pivot table.
     * Otherwise, evaluates rules dynamically.
     *
     * @return Collection<int, Song>
     */
    public function evaluate(Playlist $playlist, ?User $user = null): Collection
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return new Collection();
        }

        // Use materialized results if available and up-to-date
        if ($playlist->materialized_at !== null && !$playlist->needsRematerialization()) {
            return $playlist->songs()
                ->with(['artist', 'album', 'genres', 'tags'])
                ->get();
        }

        // Fall back to dynamic evaluation
        $query = Song::query()->with(['artist', 'album', 'genres', 'tags']);

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        return $query->get();
    }

    /**
     * Evaluate a smart playlist dynamically without using materialized results.
     *
     * This is used by RefreshSmartPlaylistJob to get fresh results.
     *
     * @return Collection<int, Song>
     */
    public function evaluateDynamic(Playlist $playlist, ?User $user = null): Collection
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return new Collection();
        }

        $query = Song::query()->with(['artist', 'album', 'genres', 'tags']);

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        return $query->get();
    }

    /**
     * Check if a single song matches the playlist rules.
     *
     * This is used for incremental updates when a song changes.
     */
    public function matches(Playlist $playlist, Song $song, ?User $user = null): bool
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return false;
        }

        $query = Song::query()->where('id', $song->id);

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        return $query->exists();
    }

    /**
     * Count matching songs for a smart playlist without loading them.
     */
    public function count(Playlist $playlist, ?User $user = null): int
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return 0;
        }

        $query = Song::query();

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        return $query->count();
    }

    /**
     * Calculate total length of matching songs for a smart playlist.
     */
    public function totalLength(Playlist $playlist, ?User $user = null): float
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return 0;
        }

        $query = Song::query();

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        return (float) $query->sum('length');
    }

    /**
     * Evaluate a smart playlist with pagination support.
     *
     * Returns both the paginated songs and total count for efficient pagination.
     *
     * @return array{songs: Collection<int, Song>, total: int}
     */
    public function evaluatePaginated(Playlist $playlist, ?User $user, int $limit, int $offset): array
    {
        if (!$playlist->is_smart || empty($playlist->rules)) {
            return ['songs' => new Collection(), 'total' => 0];
        }

        $query = Song::query();

        foreach ($playlist->rules as $ruleGroup) {
            $this->applyRuleGroup($query, $ruleGroup, $user);
        }

        $total = $query->count();

        $songs = (clone $query)
            ->with(['artist', 'album', 'genres', 'tags'])
            ->skip($offset)
            ->take($limit)
            ->get();

        return ['songs' => $songs, 'total' => $total];
    }

    /**
     * Apply a rule group to the query.
     *
     * @param Builder<Song> $query
     * @param array{id?: int, logic: string, rules: array<array{field: string, operator: string, value: mixed}>} $ruleGroup
     */
    private function applyRuleGroup(Builder $query, array $ruleGroup, ?User $user): void
    {
        $logic = strtolower($ruleGroup['logic']);
        $rules = $ruleGroup['rules'];

        $query->where(function (Builder $groupQuery) use ($rules, $logic, $user) {
            foreach ($rules as $rule) {
                $method = $logic === 'or' ? 'orWhere' : 'where';
                $this->applyRule($groupQuery, $rule, $method, $user);
            }
        });
    }

    /**
     * Apply a single rule to the query.
     *
     * @param Builder<Song> $query
     * @param array{field: string, operator: string, value: mixed} $rule
     */
    private function applyRule(Builder $query, array $rule, string $method, ?User $user): void
    {
        $field = SmartPlaylistField::tryFrom($rule['field']);
        $operator = SmartPlaylistOperator::tryFrom($rule['operator']);
        $value = $rule['value'];

        if ($field === null || $operator === null) {
            return;
        }

        // Special handling for play count (needs user context)
        if ($field === SmartPlaylistField::PLAY_COUNT && $user) {
            $this->applyPlayCountRule($query, $operator, $value, $method, $user);
            return;
        }

        // Special handling for last played (needs user context)
        if ($field === SmartPlaylistField::LAST_PLAYED && $user) {
            $this->applyLastPlayedRule($query, $operator, $value, $method, $user);
            return;
        }

        // Special handling for is_favorite (needs user context)
        if ($field === SmartPlaylistField::IS_FAVORITE && $user) {
            $this->applyIsFavoriteRule($query, $operator, $method, $user);
            return;
        }

        // Special handling for tags (relationship-based)
        if ($field === SmartPlaylistField::TAG) {
            $this->applyTagRule($query, $operator, $value, $method);
            return;
        }

        $column = $field->column();

        match ($operator) {
            SmartPlaylistOperator::IS => $query->$method($column, '=', $value),
            SmartPlaylistOperator::IS_NOT => $query->$method($column, '!=', $value),
            SmartPlaylistOperator::CONTAINS => $query->$method($column, 'ilike', "%{$value}%"),
            SmartPlaylistOperator::NOT_CONTAINS => $query->$method($column, 'not ilike', "%{$value}%"),
            SmartPlaylistOperator::BEGINS_WITH => $query->$method($column, 'ilike', "{$value}%"),
            SmartPlaylistOperator::ENDS_WITH => $query->$method($column, 'ilike', "%{$value}"),
            SmartPlaylistOperator::IS_GREATER_THAN => $query->$method($column, '>', $value),
            SmartPlaylistOperator::IS_LESS_THAN => $query->$method($column, '<', $value),
            SmartPlaylistOperator::IS_BETWEEN => $this->applyBetween($query, $column, $value, $method),
            SmartPlaylistOperator::IN_LAST => $this->applyInLast($query, $column, $value, $method),
            SmartPlaylistOperator::NOT_IN_LAST => $this->applyNotInLast($query, $column, $value, $method),
        };
    }

    /**
     * Apply play count rule.
     *
     * @param Builder<Song> $query
     */
    private function applyPlayCountRule(Builder $query, SmartPlaylistOperator $operator, mixed $value, string $method, User $user): void
    {
        $query->$method(function (Builder $q) use ($operator, $value, $user) {
            $q->whereHas('interactions', function (Builder $iq) use ($operator, $value, $user) {
                $iq->where('user_id', $user->id);
                match ($operator) {
                    SmartPlaylistOperator::IS => $iq->where('play_count', '=', $value),
                    SmartPlaylistOperator::IS_NOT => $iq->where('play_count', '!=', $value),
                    SmartPlaylistOperator::IS_GREATER_THAN => $iq->where('play_count', '>', $value),
                    SmartPlaylistOperator::IS_LESS_THAN => $iq->where('play_count', '<', $value),
                    SmartPlaylistOperator::IS_BETWEEN => is_array($value) ? $iq->whereBetween('play_count', $value) : null,
                    default => null,
                };
            });
        });
    }

    /**
     * Apply last played rule.
     *
     * @param Builder<Song> $query
     */
    private function applyLastPlayedRule(Builder $query, SmartPlaylistOperator $operator, mixed $value, string $method, User $user): void
    {
        $days = (int) $value;
        $threshold = now()->subDays($days);

        $query->$method(function (Builder $q) use ($operator, $threshold, $user) {
            match ($operator) {
                SmartPlaylistOperator::IN_LAST => $q->whereHas('interactions', fn ($iq) =>
                    $iq->where('user_id', $user->id)->where('last_played_at', '>=', $threshold)
                ),
                SmartPlaylistOperator::NOT_IN_LAST => $q->whereDoesntHave('interactions', fn ($iq) =>
                    $iq->where('user_id', $user->id)->where('last_played_at', '>=', $threshold)
                ),
                default => null,
            };
        });
    }

    /**
     * Apply between operator.
     *
     * @param Builder<Song> $query
     */
    private function applyBetween(Builder $query, string $column, mixed $value, string $method): void
    {
        if (is_array($value) && count($value) === 2) {
            $query->$method(function (Builder $q) use ($column, $value) {
                $q->whereBetween($column, $value);
            });
        }
    }

    /**
     * Apply "in last X days" operator.
     *
     * @param Builder<Song> $query
     */
    private function applyInLast(Builder $query, string $column, mixed $value, string $method): void
    {
        $days = (int) $value;
        $query->$method($column, '>=', now()->subDays($days));
    }

    /**
     * Apply "not in last X days" operator.
     *
     * @param Builder<Song> $query
     */
    private function applyNotInLast(Builder $query, string $column, mixed $value, string $method): void
    {
        $days = (int) $value;
        $query->$method(function (Builder $q) use ($column, $days) {
            $q->whereNull($column)
                ->orWhere($column, '<', now()->subDays($days));
        });
    }

    /**
     * Apply is_favorite rule.
     *
     * Uses raw SQL to handle PostgreSQL UUID vs varchar comparison.
     * The favorites table uses varchar for favoritable_id (polymorphic),
     * but songs.id is UUID type, requiring explicit cast.
     *
     * @param Builder<Song> $query
     */
    private function applyIsFavoriteRule(Builder $query, SmartPlaylistOperator $operator, string $method, User $user): void
    {
        $morphType = (new Song())->getMorphClass();

        match ($operator) {
            SmartPlaylistOperator::IS => $query->$method(function (Builder $q) use ($user, $morphType) {
                $q->whereExists(function ($subquery) use ($user, $morphType) {
                    $subquery->selectRaw('1')
                        ->from('favorites')
                        ->whereColumn('favorites.favoritable_id', '=', \DB::raw('songs.id::text'))
                        ->where('favorites.favoritable_type', $morphType)
                        ->where('favorites.user_id', $user->id);
                });
            }),
            SmartPlaylistOperator::IS_NOT => $query->$method(function (Builder $q) use ($user, $morphType) {
                $q->whereNotExists(function ($subquery) use ($user, $morphType) {
                    $subquery->selectRaw('1')
                        ->from('favorites')
                        ->whereColumn('favorites.favoritable_id', '=', \DB::raw('songs.id::text'))
                        ->where('favorites.favoritable_type', $morphType)
                        ->where('favorites.user_id', $user->id);
                });
            }),
            default => null, // Ignore unsupported operators
        };
    }

    /**
     * Apply tag rule.
     *
     * @param Builder<Song> $query
     */
    private function applyTagRule(Builder $query, SmartPlaylistOperator $operator, mixed $value, string $method): void
    {
        $tagName = (string) $value;

        match ($operator) {
            SmartPlaylistOperator::IS => $query->$method(function (Builder $q) use ($tagName) {
                $q->whereHas('tags', fn (Builder $tq) => $tq->where('name', $tagName));
            }),
            SmartPlaylistOperator::IS_NOT => $query->$method(function (Builder $q) use ($tagName) {
                $q->whereDoesntHave('tags', fn (Builder $tq) => $tq->where('name', $tagName));
            }),
            SmartPlaylistOperator::CONTAINS => $query->$method(function (Builder $q) use ($tagName) {
                $q->whereHas('tags', fn (Builder $tq) => $tq->where('name', 'ilike', "%{$tagName}%"));
            }),
            SmartPlaylistOperator::NOT_CONTAINS => $query->$method(function (Builder $q) use ($tagName) {
                $q->whereDoesntHave('tags', fn (Builder $tq) => $tq->where('name', 'ilike', "%{$tagName}%"));
            }),
            default => null,
        };
    }
}
