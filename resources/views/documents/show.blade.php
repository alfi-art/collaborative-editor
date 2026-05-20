<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $document->title }} - Collaborative Editor</title>
    
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- WebSocket -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.0/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@7.0.3/dist/web/pusher.min.js"></script>
    
    <style>
        #editor-container {
            height: calc(100vh - 180px);
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .saving-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            transition: opacity 0.3s;
            z-index: 100;
        }
        .remote-cursor {
            position: absolute;
            width: 2px;
            height: 20px;
            background: currentColor;
            pointer-events: none;
            z-index: 1000;
        }
        .remote-cursor-label {
            position: absolute;
            top: -18px;
            left: 0;
            background: currentColor;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
        }
        .participants-panel {
            position: fixed;
            right: 20px;
            top: 100px;
            width: 200px;
            background: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .participant-item {
            display: flex;
            align-items: center;
            padding: 4px 0;
            font-size: 13px;
        }
        .participant-color {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        #version-panel {
            position: fixed;
            right: 20px;
            top: 100px;
            width: 300px;
            background: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            z-index: 200;
            max-height: 80vh;
            overflow-y: auto;
        }
        #version-panel.show {
            display: block;
        }
        .version-item {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }
        .btn-restore {
            background: #eab308;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-restore:hover {
            background: #ca8a04;
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $document->title }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Editing as <span class="font-semibold">{{ $userName }}</span>
                    </p>
                </div>
                <div class="space-x-3">
                    <button onclick="toggleHistory()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                        📜 Version History
                    </button>
                    <a href="/dashboard" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Editor -->
    <div class="container mx-auto px-6 py-6">
        <div id="editor-container"></div>
    </div>
    
    <!-- Active Users Panel -->
    <div class="participants-panel">
        <h3 class="font-bold mb-2 text-sm">👥 Active Users</h3>
        <div id="participants-list">
            <div class="participant-item" id="participant-{{ $userId }}">
                <div class="participant-color" style="background: {{ $userColor }}"></div>
                <span>{{ $userName }} (You)</span>
            </div>
            @foreach($activeParticipants as $participant)
                @if($participant->user_id != $userId)
                <div class="participant-item" id="participant-{{ $participant->user_id }}">
                    <div class="participant-color" style="background: {{ $participant->cursor_color }}"></div>
                    <span>{{ $participant->user->name }}</span>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    
    <!-- Version History Panel -->
    <div id="version-panel">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold">📜 Version History</h3>
            <button onclick="toggleHistory()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        <div id="history-list">Loading...</div>
    </div>
    
    <!-- Saving Status -->
    <div id="saving-status" class="saving-status opacity-0">💾 Saving...</div>
    
    <script>
        const documentId = {{ $document->id }};
        const userId = {{ $userId }};
        const userName = '{{ $userName }}';
        const userColor = '{{ $userColor }}';
        
        let quill, saveTimeout, currentContent, isLocalChange = false;
        
        // Load content
        let initialContent = @json($document->content);
        try { initialContent = JSON.parse(initialContent); } catch(e) { initialContent = { ops: [{ insert: "\n" }] }; }
        
        // Init Quill
        quill = new Quill('#editor-container', { theme: 'snow', modules: { toolbar: true } });
        quill.setContents(initialContent);
        currentContent = initialContent;
        
        // Auto-save
        quill.on('text-change', function(delta, oldDelta, source) {
            if (source === 'user') {
                isLocalChange = true;
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveContent, 500);
                document.getElementById('saving-status').classList.remove('opacity-0');
                setTimeout(() => document.getElementById('saving-status').classList.add('opacity-0'), 1000);
            }
        });
        
        async function saveContent() {
            const content = JSON.stringify(quill.getContents());
            await fetch(`/documents/${documentId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ content: content })
            });
        }
        
        // WebSocket
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ env("REVERB_APP_KEY") }}',
            wsHost: '{{ env("REVERB_HOST", "localhost") }}',
            wsPort: {{ env("REVERB_PORT", 8080) }},
            forceTLS: false,
            enabledTransports: ['ws']
        });
        
        // Real-time update
        window.Echo.channel(`document.${documentId}`)
            .listen('DocumentUpdate', (e) => {
                if (e.userId !== userId) {
                    isLocalChange = true;
                    try { quill.setContents(JSON.parse(e.content)); } catch(err) {}
                    setTimeout(() => { isLocalChange = false; }, 100);
                }
            });
        
        // Live cursor
        quill.on('selection-change', function(range) {
            if (range) {
                fetch(`/documents/${documentId}/cursor`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ position: { index: range.index }, color: userColor })
                });
            }
        });
        
        window.Echo.channel(`document.${documentId}`)
            .listen('CursorMoved', (e) => {
                if (e.userId !== userId) {
                    const old = document.getElementById(`cursor-${e.userId}`);
                    if (old) old.remove();
                    
                    const cursor = document.createElement('div');
                    cursor.id = `cursor-${e.userId}`;
                    cursor.className = 'remote-cursor';
                    cursor.style.color = e.color;
                    const label = document.createElement('div');
                    label.className = 'remote-cursor-label';
                    label.innerText = e.userName;
                    label.style.backgroundColor = e.color;
                    cursor.appendChild(label);
                    cursor.style.top = '20px';
                    cursor.style.left = `${e.position.index * 8}px`;
                    document.querySelector('.ql-editor')?.appendChild(cursor);
                }
            });
        
        // User joined
        window.Echo.channel(`document.${documentId}`)
            .listen('UserJoined', (e) => {
                if (e.user.id !== userId) {
                    const container = document.getElementById('participants-list');
                    if (!document.getElementById(`participant-${e.user.id}`)) {
                        const div = document.createElement('div');
                        div.id = `participant-${e.user.id}`;
                        div.className = 'participant-item';
                        div.innerHTML = `<div class="participant-color" style="background: ${e.color}"></div><span>${e.user.name}</span>`;
                        container.appendChild(div);
                    }
                }
            });
        
        // Version History functions
        function toggleHistory() {
            const panel = document.getElementById('version-panel');
            panel.classList.toggle('show');
            if (panel.classList.contains('show')) loadHistory();
        }
        
        async function loadHistory() {
            const res = await fetch(`/documents/${documentId}/revisions`);
            const revisions = await res.json();
            const container = document.getElementById('history-list');
            if (revisions.length === 0) { container.innerHTML = '<p class="text-gray-500">No revisions</p>'; return; }
            
            let html = '';
            for (let rev of revisions) {
                html += `
                    <div class="version-item">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-bold">v${rev.version_number}</span>
                                <div class="text-xs text-gray-500">${rev.user?.name || 'Unknown'}</div>
                                <div class="text-xs text-gray-400">${new Date(rev.created_at).toLocaleString()}</div>
                            </div>
                            <button onclick="rollback(${rev.id})" class="btn-restore">Restore</button>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }
        
        async function rollback(revisionId) {
            if (!confirm('Restore to this version?')) return;
            await fetch(`/documents/${documentId}/rollback/${revisionId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            location.reload();
        }
        
        console.log('Editor ready!');
    </script>
</body>
</html>