<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $document->title }} - Collaborative Editor</title>
    
    <!-- Quill Editor CSS & JS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
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
        .version-history {
            position: fixed;
            right: 20px;
            top: 100px;
            width: 260px;
            background: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 70vh;
            overflow-y: auto;
            display: none;
        }
        .version-history.show {
            display: block;
        }
        .version-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .version-item:hover {
            background: #f5f5f5;
        }
        .ql-editor {
            font-size: 16px;
            line-height: 1.6;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3B82F6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563EB;
        }
        .btn-secondary {
            background: #6B7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4B5563;
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
                    <button onclick="toggleHistory()" class="btn btn-secondary">
                        📜 Version History
                    </button>
                    <a href="/dashboard" class="btn btn-primary">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Editor Container -->
    <div class="container mx-auto px-6 py-6">
        <div id="editor-container"></div>
    </div>
    
    <!-- Saving Status -->
    <div id="saving-status" class="saving-status opacity-0">
        💾 Saving...
    </div>
    
    <!-- Version History Panel -->
    <div id="version-panel" class="version-history">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-lg">Version History</h3>
            <button onclick="toggleHistory()" class="text-gray-500 hover:text-gray-700">✕</button>
        </div>
        <div id="history-list">
            <p class="text-gray-500 text-sm">Loading...</p>
        </div>
    </div>
    
    <script>
        // Data dari server
        const documentId = {{ $document->id }};
        const userId = {{ $userId }};
        const userName = '{{ $userName }}';
        const userColor = '{{ $userColor }}';
        
        // Variabel global
        let quill;
        let saveTimeout;
        let currentContent;
        
        // Load initial content
        let initialContent = @json($document->content);
        try {
            initialContent = JSON.parse(initialContent);
        } catch(e) {
            initialContent = { ops: [{ insert: "\n" }] };
        }
        
        // Initialize Quill Editor
        quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Start typing here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            }
        });
        
        // Set content
        quill.setContents(initialContent);
        currentContent = initialContent;
        
        // Auto-save on text change
        quill.on('text-change', function(delta, oldDelta, source) {
            if (source === 'user') {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveContent, 500);
                
                // Show saving indicator
                const status = document.getElementById('saving-status');
                status.classList.remove('opacity-0');
                setTimeout(() => status.classList.add('opacity-0'), 1000);
            }
        });
        
        // Save content to server
        async function saveContent() {
            const content = JSON.stringify(quill.getContents());
            
            try {
                const response = await fetch(`/documents/${documentId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content: content })
                });
                
                if (response.ok) {
                    currentContent = content;
                }
            } catch (error) {
                console.error('Save error:', error);
            }
        }
        
        // Toggle version history panel
        function toggleHistory() {
            const panel = document.getElementById('version-panel');
            panel.classList.toggle('show');
            if (panel.classList.contains('show')) {
                loadHistory();
            }
        }
        
        // Load version history
        async function loadHistory() {
            try {
                const response = await fetch(`/documents/${documentId}/revisions`);
                const revisions = await response.json();
                
                const container = document.getElementById('history-list');
                if (revisions.length === 0) {
                    container.innerHTML = '<p class="text-gray-500 text-sm">No revisions yet.</p>';
                    return;
                }
                
                let html = '';
                for (let rev of revisions) {
                    let metadata = '';
                    if (rev.metadata) {
                        if (typeof rev.metadata === 'string') {
                            try {
                                metadata = JSON.parse(rev.metadata);
                            } catch(e) {}
                        } else {
                            metadata = rev.metadata;
                        }
                    }
                    
                    html += `
                        <div class="version-item">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-bold text-sm">v${rev.version_number}</span>
                                    <div class="text-xs text-gray-500">by ${rev.user?.name || 'Unknown'}</div>
                                    <div class="text-xs text-gray-400">${new Date(rev.created_at).toLocaleString()}</div>
                                    ${metadata.user ? `<div class="text-xs text-gray-500 mt-1">✏️ ${metadata.user}</div>` : ''}
                                </div>
                                <button onclick="rollbackToVersion(${rev.id})" 
                                        class="text-xs bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600">
                                    Restore
                                </button>
                            </div>
                        </div>
                    `;
                }
                container.innerHTML = html;
                
            } catch (error) {
                console.error('Error loading history:', error);
                document.getElementById('history-list').innerHTML = '<p class="text-red-500 text-sm">Failed to load history</p>';
            }
        }
        
        // Rollback to specific version
        async function rollbackToVersion(revisionId) {
            if (!confirm('Restore to this version? Current changes will be saved as a new revision.')) return;
            
            try {
                const response = await fetch(`/documents/${documentId}/rollback/${revisionId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    alert('Rollback successful! Page will reload.');
                    location.reload();
                } else {
                    alert('Failed to rollback');
                }
            } catch (error) {
                console.error('Rollback error:', error);
                alert('Failed to rollback');
            }
        }
        
        // Auto-save before leaving page
        window.addEventListener('beforeunload', function() {
            if (JSON.stringify(quill.getContents()) !== JSON.stringify(currentContent)) {
                saveContent();
            }
        });
        
        console.log('Editor ready! Document ID: ' + documentId);
    </script>
</body>
</html>