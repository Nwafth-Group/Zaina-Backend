<?php 

namespace App\Service;
use App\Models\LegalDocument;

class LegalService {

public function getDocumentsByType(string $type)
    {
        return LegalDocument::where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

};