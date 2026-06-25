<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaseDocument\StoreCaseDocumentRequest;
use App\Http\Requests\CaseDocument\UpdateCaseDocumentRequest;
use App\Models\CaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CaseDocumentController extends Controller
{
    public function index(): JsonResponse
    {
        $documents = CaseDocument::with(['legalCase', 'uploader'])
            ->latest()
            ->paginate(15);

        return $this->success($documents);
    }

    public function store(StoreCaseDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('case-documents', 'public');

        $document = CaseDocument::create([
            'case_id' => $request->case_id,
            'uploaded_by' => $request->user()->id,
            'title' => $request->title,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'notes' => $request->notes,
        ]);

        $document->load(['legalCase', 'uploader']);

        return $this->success($document, 'Document uploaded successfully', 201);
    }

    public function show(CaseDocument $caseDocument): JsonResponse
    {
        $caseDocument->load(['legalCase', 'uploader']);

        return $this->success($caseDocument);
    }

    public function update(UpdateCaseDocumentRequest $request, CaseDocument $caseDocument): JsonResponse
    {
        $data = $request->validated();
        unset($data['file']);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($caseDocument->file_path);

            $file = $request->file('file');
            $data['file_path'] = $file->store('case-documents', 'public');
            $data['file_type'] = $file->getClientMimeType();
        }

        $caseDocument->update($data);
        $caseDocument->load(['legalCase', 'uploader']);

        return $this->success($caseDocument, 'Document updated successfully');
    }

    public function destroy(CaseDocument $caseDocument): JsonResponse
    {
        Storage::disk('public')->delete($caseDocument->file_path);
        $caseDocument->delete();

        return $this->success(null, 'Document deleted successfully');
    }
}
