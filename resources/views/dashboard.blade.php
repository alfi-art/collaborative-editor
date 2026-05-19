@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">My Documents</h1>
                    <form action="/documents" method="POST">
                        @csrf
                        <input type="text" name="title" placeholder="Document title" class="border rounded px-3 py-1 mr-2" required>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded">+ New Document</button>
                    </form>
                </div>
                
                @if($documents->isEmpty())
                    <p class="text-gray-500 text-center py-8">No documents yet. Create your first document!</p>
                @else
                    @foreach($documents as $doc)
                        <div class="border p-4 mb-3 rounded-lg hover:shadow transition">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold text-lg">{{ $doc->title }}</h3>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <span>Version {{ $doc->current_version }}</span>
                                        <span class="mx-2">•</span>
                                        <span>Created by {{ $doc->owner->name ?? 'Unknown' }}</span>
                                        <span class="mx-2">•</span>
                                        <span>{{ $doc->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <a href="/documents/{{ $doc->id }}/edit" 
                                   class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 transition">
                                    Open Document →
                                </a>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection