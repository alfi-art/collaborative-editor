@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">My Documents</h1>
                    <form action="/documents" method="POST" class="flex gap-2">
                        @csrf
                        <input type="text" name="title" placeholder="Document title" class="border rounded px-3 py-2" required>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">+ New</button>
                    </form>
                </div>
                
                @if($documents->isEmpty())
                    <p class="text-gray-500">No documents yet.</p>
                @else
                    @foreach($documents as $doc)
                    <div style="border:1px solid #ddd; padding:12px; margin-bottom:10px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="font-size:16px;">{{ $doc->title }}</strong>
                            <span style="color:gray; margin-left:8px;">v{{ $doc->current_version }}</span>
                            <span style="color:gray; margin-left:8px;">by {{ $doc->owner->name ?? 'Unknown' }}</span>
                        </div>
                        <div>
                            <a href="/documents/{{ $doc->id }}/edit" 
                               style="background-color: #22c55e; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; margin-right:8px;">
                                OPEN
                            </a>
                            <form action="/documents/{{ $doc->id }}" 
                                  method="POST" 
                                  onsubmit="return confirm('Delete {{ $doc->title }}?')"
                                  style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        style="background-color: #ef4444; color:white; padding:6px 12px; border-radius:6px; border:none; cursor:pointer;">
                                    DELETE
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection