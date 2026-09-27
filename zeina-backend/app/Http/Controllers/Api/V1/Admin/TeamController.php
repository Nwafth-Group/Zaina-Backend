<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreTeamRequest;
use App\Http\Requests\Api\V1\Admin\UpdateTeamRequest;
use App\Http\Resources\Api\V1\Team\TeamResource;
use App\Models\Team;
use App\Models\Worker;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of teams.
     */
    public function index(Request $request)
    {
        $query = Team::query()->with(['workers.media', 'services']);

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Service filter
        if ($request->filled('service_id')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('services.id', $request->input('service_id'));
            });
        }

        // Sorting
        $allowedSorts = ['name_ar', 'name_en', 'code', 'status', 'created_at', 'id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Counts
        $counts = [
            'all' => Team::count(),
            'active' => Team::where('status', 'active')->count(),
            'inactive' => Team::where('status', 'inactive')->count(),
            'total_workers' => Worker::whereNotNull('team_id')->count(),
        ];

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = (int) $request->input('per_page', 10);
            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Teams retrieved successfully',
                'data' => [
                    'items' => TeamResource::collection($paginated->items()),
                    'pagination' => [
                        'total' => $paginated->total(),
                        'per_page' => $paginated->perPage(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'from' => $paginated->firstItem(),
                        'to' => $paginated->lastItem(),
                    ],
                    'meta' => [
                        'total' => $paginated->total(),
                        'per_page' => $paginated->perPage(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'from' => $paginated->firstItem(),
                        'to' => $paginated->lastItem(),
                    ],
                    'counts' => $counts,
                ],
            ]);
        }

        $teams = $query->get();
        return response()->json([
            'success' => true,
            'message' => 'Teams retrieved successfully',
            'data' => [
                'items' => TeamResource::collection($teams),
                'counts' => $counts,
            ],
        ]);
    }

    /**
     * Store a newly created team in storage.
     */
    public function store(StoreTeamRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            $team = Team::create([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'code' => $data['code'],
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            // Assign workers to team
            if (!empty($data['worker_ids'])) {
                Worker::whereIn('id', $data['worker_ids'])->update(['team_id' => $team->id]);
            }

            // Sync services
            if (isset($data['service_ids'])) {
                $team->services()->sync($data['service_ids']);

                // Auto-sync team workers to these services
                if (!empty($data['worker_ids'])) {
                    foreach ($team->services as $service) {
                        $service->workers()->syncWithoutDetaching($data['worker_ids']);
                    }
                }
            }

            $team->load(['workers.media', 'services']);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفريق بنجاح',
                'data' => new TeamResource($team),
            ], 201);
        });
    }

    /**
     * Display the specified team.
     */
    public function show(Team $team)
    {
        $team->load(['workers.media', 'services']);

        return $this->successResponse(new TeamResource($team), 'Team details retrieved successfully');
    }

    /**
     * Update the specified team in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team)
    {
        return DB::transaction(function () use ($request, $team) {
            $data = $request->validated();

            $team->update([
                'name_ar' => $data['name_ar'] ?? $team->name_ar,
                'name_en' => $data['name_en'] ?? $team->name_en,
                'code' => $data['code'] ?? $team->code,
                'status' => $data['status'] ?? $team->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $team->notes,
            ]);

            // Sync workers if key is provided
            if (array_key_exists('worker_ids', $data)) {
                $newWorkerIds = $data['worker_ids'] ?? [];

                // Remove workers unassigned from this team
                Worker::where('team_id', $team->id)
                    ->whereNotIn('id', $newWorkerIds)
                    ->update(['team_id' => null]);

                // Assign selected workers
                if (!empty($newWorkerIds)) {
                    Worker::whereIn('id', $newWorkerIds)->update(['team_id' => $team->id]);
                }
            }

            // Sync services if key is provided
            if (array_key_exists('service_ids', $data)) {
                $team->services()->sync($data['service_ids'] ?? []);

                // Auto-sync team workers to services
                $currentWorkerIds = $team->workers()->pluck('id')->toArray();
                if (!empty($currentWorkerIds)) {
                    foreach ($team->services as $service) {
                        $service->workers()->syncWithoutDetaching($currentWorkerIds);
                    }
                }
            }

            $team->load(['workers.media', 'services']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الفريق بنجاح',
                'data' => new TeamResource($team),
            ]);
        });
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Team $team)
    {
        return DB::transaction(function () use ($team) {
            // Unassign workers
            Worker::where('team_id', $team->id)->update(['team_id' => null]);

            // Detach services
            $team->services()->detach();

            $team->delete();

            return $this->successResponse(null, 'تم حذف الفريق بنجاح');
        });
    }
}
