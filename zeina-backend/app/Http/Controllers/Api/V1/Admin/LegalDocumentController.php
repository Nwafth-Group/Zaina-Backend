<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreLegalDocumentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLegalDocumentRequest;
use App\Http\Resources\Api\V1\LegalDocument\AdminLegalDocumentResource;
use App\Models\LegalDocument;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LegalDocumentController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of legal documents.
     */
    public function index(Request $request)
    {
        $type = $request->input('type'); // optional filter: terms, privacy, or about_us
        
        $query = LegalDocument::query()->orderBy('sort_order', 'asc');

        if ($type) {
            $query->where('type', $type);
        }

        $documents = $query->get();

        return $this->successResponse(
            AdminLegalDocumentResource::collection($documents),
            'Legal documents fetched successfully.'
        );
    }

    /**
     * Store a newly created legal document in storage.
     */
    public function store(StoreLegalDocumentRequest $request)
    {
        $document = LegalDocument::create($request->validated());

        Log::info("Admin created a new legal document section ID: {$document->id}");

        return $this->successResponse(
            new AdminLegalDocumentResource($document),
            'Legal document created successfully.',
            201
        );
    }

    /**
     * Display the specified legal document.
     */
    public function show($id)
    {
        $document = LegalDocument::find($id);

        if (!$document) {
            return $this->errorResponse('Legal document section not found.', 404);
        }

        return $this->successResponse(
            new AdminLegalDocumentResource($document),
            'Legal document fetched successfully.'
        );
    }

    /**
     * Update the specified legal document in storage.
     */
    public function update(UpdateLegalDocumentRequest $request, $id)
    {
        $document = LegalDocument::find($id);

        if (!$document) {
            return $this->errorResponse('Legal document section not found.', 404);
        }

        $document->update($request->validated());

        Log::info("Admin updated legal document section ID: {$id}");

        return $this->successResponse(
            new AdminLegalDocumentResource($document),
            'Legal document updated successfully.'
        );
    }

    /**
     * Remove the specified legal document from storage.
     */
    public function destroy($id)
    {
        $document = LegalDocument::find($id);

        if (!$document) {
            return $this->errorResponse('Legal document section not found.', 404);
        }

        $document->delete();

        Log::info("Admin deleted legal document section ID: {$id}");

        return $this->successResponse(
            null,
            'Legal document deleted successfully.'
        );
    }
}
