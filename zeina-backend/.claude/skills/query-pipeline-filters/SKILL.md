---
name: query-pipeline-filters
description: "Apply when building or refactoring list/index endpoints that filter an Eloquent query by request parameters. Use whenever a controller index method accumulates conditional `if ($request->filled('x')) { $query->where(...) }` blocks, or when adding search / status / relationship / range filters to a paginated listing. Implements reusable, single-responsibility filter classes run through Illuminate\\Pipeline so controllers stay thin and each filter is independently testable and reusable across admin and customer queries."
license: MIT
metadata:
  author: zaina
---

# Query Pipeline Filters

Filter Eloquent list queries with the **Pipeline pattern**: one small class per filter,
composed through `Illuminate\Pipeline\Pipeline`. Each filter is single-responsibility,
applies only when its request key is present, and is reusable across queries.

Canonical implementation in this repo (use it as the template):
- Base: `app/Filters/Filter.php`
- Pipes: `app/Filters/Worker/{Search,Status,Nationality,Service,MinRating}Filter.php`
- Usage: `App\Http\Controllers\Api\V1\Admin\WorkerController@index`
- Tests: `tests/Feature/Admin/WorkerListingFilterTest.php`

## When to use

- A listing/index endpoint with **2+ optional filters** driven by query params.
- A controller `index` growing a chain of `if ($request->filled(...))` `where` blocks.
- Filters you want to **reuse** (e.g. the same `status`/`service` filter on admin and
  customer queries).

**When NOT to use:** a single trivial filter, or a one-off query. A lone
`->when($request->status, ...)` is fine — don't add classes for one condition.

## Structure

```
app/Filters/
  Filter.php                 # abstract base (applies only when key is present)
  Worker/
    SearchFilter.php
    StatusFilter.php
    NationalityFilter.php
    ServiceFilter.php
    MinRatingFilter.php
```

One folder per domain/model (`Worker`, `Service`, `Booking`, …). Filters return `void`
and mutate the passed `Builder`; the base handles the "only apply when present" + `$next`.

## The base class

```php
namespace App\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

    abstract protected function key(): string;

    abstract protected function apply(Builder $query, mixed $value): void;
}
```

## A filter pipe

A scalar `where` filter:

```php
namespace App\Filters\Worker;

use App\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends Filter
{
    protected function key(): string
    {
        return 'status';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->where('status', $value);
    }
}
```

A relationship filter (filter workers by a linked service via the `service_workers` pivot):

```php
class ServiceFilter extends Filter
{
    protected function key(): string
    {
        return 'service_id';
    }

    protected function apply(Builder $query, mixed $value): void
    {
        $query->whereHas('services', function (Builder $q) use ($value): void {
            $q->where('services.id', $value);
        });
    }
}
```

## Controller usage

```php
use App\Filters\Worker\MinRatingFilter;
use App\Filters\Worker\NationalityFilter;
use App\Filters\Worker\SearchFilter;
use App\Filters\Worker\ServiceFilter;
use App\Filters\Worker\StatusFilter;
use Illuminate\Pipeline\Pipeline;

public function index(Request $request)
{
    $query = app(Pipeline::class)
        ->send(Worker::query()->with('media'))
        ->through([
            SearchFilter::class,
            StatusFilter::class,
            NationalityFilter::class,
            ServiceFilter::class,
            MinRatingFilter::class,
        ])
        ->thenReturn();

    // Sorting and pagination stay in the controller — they are presentation,
    // not filters. Always allow-list sortable columns to avoid SQL injection.
    $allowedSorts = ['name', 'avg_rating', 'created_at', 'id'];
    $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
    $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $query->orderBy($sortBy, $sortDir);

    $perPage = (int) $request->input('per_page', 10);
    $workers = $query->paginate($perPage)->withQueryString();

    return $this->successResponse(WorkerResource::collection($workers), __('messages.workers_retrieved_successfully'));
}
```

> DI note: `app(Pipeline::class)` is the common form. To satisfy strict "no `app()`
> inside classes", inject it instead — `public function index(Request $request, Pipeline $pipeline)`
> — Laravel resolves `Pipeline` via method injection.

## Adding a new filter

1. Create `app/Filters/<Domain>/<Name>Filter.php` extending `App\Filters\Filter`.
2. Return its query-string key from `key()`; mutate the `Builder` in `apply()`.
3. Add the class to the controller's `->through([...])` array.
4. Add a feature test asserting the filter narrows results (see below).

No controller `if` blocks, no touching the base class — the pattern is open for extension.

## Testing

Feature-test each filter end-to-end against the real endpoint (the behavior is identical
whether implemented as a pipe or an inline `where`, so these tests also guard refactors):

```php
public function test_filter_by_linked_service(): void
{
    $this->actingAsAdmin();
    $service = Service::factory()->create();
    $linked = Worker::factory()->create(['name' => 'Linked']);
    $linked->services()->attach($service->id);
    Worker::factory()->create(['name' => 'Unlinked']);

    $this->getJson('/api/v1/admin/workers?service_id='.$service->id)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.name', 'Linked');
}
```

Also test a combined-filter request that narrows to a single row — it proves the pipes
compose (AND) correctly.

## Conventions & gotchas

- **Resolution:** pass class strings to `->through([...])`; the container resolves each
  pipe, so constructor-injected `Request` works automatically.
- **Apply-when-present:** the base only calls `apply()` when `$request->filled($key)` —
  pipes never need their own presence checks.
- **Order independence:** filters are ANDed `where`s, so pipe order doesn't change results;
  keep a stable, readable order (search → scalars → relationships → ranges).
- **Keep sort & pagination out of the pipeline.** They are not filters. Always allow-list
  `sort_by` columns.
- **Relationship filters:** prefer `whereHas` for correctness; for hot paths consider
  `whereIn` + `pluck` (see `laravel-best-practices` → advanced queries).
- **`withQueryString()`** on the paginator preserves active filters in page links.
- Follow project PHP conventions: curly braces always, typed params, return types,
  constructor property promotion.
