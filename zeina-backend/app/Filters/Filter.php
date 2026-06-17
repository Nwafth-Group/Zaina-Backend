<?php

namespace App\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Base query filter for the Illuminate Pipeline. A concrete filter only applies
 * itself when its request key is present, then hands the query to the next pipe.
 */
abstract class Filter
{
    public function __construct(protected Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        if ($this->request->filled($this->key())) {
            $this->apply($query, $this->request->input($this->key()));
        }

        return $next($query);
    }

    /**
     * The query-string key that activates this filter.
     */
    abstract protected function key(): string;

    /**
     * Mutate the query with the provided filter value.
     */
    abstract protected function apply(Builder $query, mixed $value): void;
}
