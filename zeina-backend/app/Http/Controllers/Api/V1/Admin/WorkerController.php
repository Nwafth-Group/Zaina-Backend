<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateWorkerRequest;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Models\Worker;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class WorkerController extends Controller
{ 
    use ApiResponseTrait;

        public function __construct(public MediaService $mediaService)
        {
        }
     /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workers = Worker::paginate(10);
        return $this->successResponse(WorkerResource::collection($workers),__('messages.workers_retrieved_successfully'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkerRequest $request)
    {
        $data = $request->validated();
        $create_data = collect($data)->only(['name', 'nationality', 'status'])->toArray();
        $worker = Worker::create($create_data);

        if(isset($data['services'])){
          $worker->services()->sync($data['services']);
          }
        if ($request->hasFile('image')) {
               $media = $this->mediaService->upload($request->file('image'), $worker, 'worker/image', 'public');
          }

        return $this->successResponse(new WorkerResource($worker),__('messages.worker_created_successfully'),201);


    }

    /**
     * Display the specified resource.
     */
    public function show(Worker $worker)
    {
        return $this->successResponse(new WorkerResource($worker),__('messages.worker_retrieved_successfully'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker)
    { 
        $data = $request->validated();
        $update_data = collect($data)->only(['name', 'nationality', 'status'])->toArray();
        $worker->update($update_data);
        if(isset($data['services'])){
          $worker->services()->sync($data['services']);
          }
        if($request->hasFile('image')) {
            if ($worker->media()->where('collection','worker/image')->exists()) {
                $oldMedia = $worker->media()->where('collection','worker/image')->first();
                $this->mediaService->delete($oldMedia);
            }
            $media = $this->mediaService->upload($request->file('image'), $worker,'worker/image', 'public');
        }
        return $this->successResponse(new WorkerResource($worker),__('messages.worker_updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function changeStatus(Worker $worker)
    {
        $worker->status = $worker->status === 'active' ? 'inactive' : 'active';
        $worker->save();

        return $this->successResponse(new WorkerResource($worker),__('messages.worker_toggle_success',['status' => $worker->status]));
    }
}
