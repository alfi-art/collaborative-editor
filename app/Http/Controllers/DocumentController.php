<?php

namespace App\Http\Controllers;

use App\Models\document;
use App\Models\DocumentParticipant;
use App\Models\DocumentRevision;
use App\Events\DocumentUpdate;
use App\Events\CursorMoved;
use App\Events\UserJoined;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = document::where('owner_id', Auth::id())->get();
        return response()->json($documents);
    }
    
    public function show($id)
    {
        $document = document::findOrFail($id);
        return response()->json($document);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);
        
        $document = document::create([
            'title' => $request->title,
            'content' => json_encode(['ops' => [['insert' => "\n"]]]),
            'owner_id' => Auth::id(),
            'current_version' => 1,
        ]);
        
        DocumentParticipant::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'last_active_at' => now(),
            'cursor_color' => '#' . substr(md5(Auth::id()), 0, 6),
        ]);
        
        DocumentRevision::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'content' => json_encode(['ops' => [['insert' => "\n"]]]),
            'version_number' => 1,
            'metadata' => json_encode(['action' => 'created', 'user' => Auth::user()->name]),
        ]);
        
        return redirect("/documents/{$document->id}/edit");
    }
    
    public function edit($id)
    {
        $document = document::findOrFail($id);
        
        $participant = DocumentParticipant::firstOrCreate(
            ['document_id' => $id, 'user_id' => Auth::id()],
            ['cursor_color' => '#' . substr(md5(Auth::id()), 0, 6)]
        );
        
        $participant->update(['last_active_at' => now()]);
        
        // Broadcast user joined (tanpa mengganggu real-time)
        try {
            broadcast(new UserJoined($id, Auth::user(), $participant->cursor_color));
        } catch(\Exception $e) {
            // Abaikan error broadcast
        }
        
        $activeParticipants = DocumentParticipant::where('document_id', $id)
            ->with('user')
            ->where('last_active_at', '>=', now()->subMinutes(5))
            ->get();
        
        return view('documents.show', [
            'document' => $document,
            'userColor' => $participant->cursor_color,
            'userId' => Auth::id(),
            'userName' => Auth::user()->name,
            'activeParticipants' => $activeParticipants,
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $document = document::findOrFail($id);
        $document->content = $request->content;
        $document->current_version++;
        $document->save();
        
        DocumentRevision::create([
            'document_id' => $id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'version_number' => $document->current_version,
            'metadata' => json_encode(['user' => Auth::user()->name]),
        ]);
        
        // BROADCAST REAL-TIME (IN YANG PENTING)
        broadcast(new DocumentUpdate(
            $id, 
            $request->content, 
            Auth::id(), 
            Auth::user()->name
        ));
        
        return response()->json(['success' => true, 'version' => $document->current_version]);
    }
    
    public function getRevisions($id)
    {
        $revisions = DocumentRevision::where('document_id', $id)
            ->with('user')
            ->orderBy('version_number', 'desc')
            ->get();
        return response()->json($revisions);
    }
    
    public function rollback($id, $revisionId)
    {
        $document = document::findOrFail($id);
        $revision = DocumentRevision::findOrFail($revisionId);
        
        $document->content = $revision->content;
        $document->current_version++;
        $document->save();
        
        DocumentRevision::create([
            'document_id' => $id,
            'user_id' => Auth::id(),
            'content' => $revision->content,
            'version_number' => $document->current_version,
            'metadata' => json_encode([
                'action' => 'rollback',
                'from_version' => $revision->version_number,
                'user' => Auth::user()->name
            ]),
        ]);
        
        broadcast(new DocumentUpdate(
            $id, 
            $revision->content, 
            Auth::id(), 
            Auth::user()->name
        ));
        
        return redirect("/documents/{$id}/edit")->with('success', 'Rollback successful!');
    }
    
    public function broadcastCursor(Request $request, $id)
    {
        try {
            broadcast(new CursorMoved(
                $id,
                auth()->id(),
                auth()->user()->name,
                $request->position,
                $request->color
            ));
        } catch(\Exception $e) {
            // Abaikan error
        }
        
        return response()->json(['success' => true]);
    }
    
    private function getDiff($old, $new)
    {
        if ($old === $new) return 'no changes';
        return 'content changed';
    }
}