<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RAG Chat</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0f0f1a;
            color: #e0e0e0;
        }

        /* ── Header ── */
        .header {
            background: #1a1a2e;
            border-bottom: 1px solid #2a2a40;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .header h1 { font-size: 16px; font-weight: 600; color: #c0c8ff; }
        .header .badges { display: flex; gap: 8px; }
        .badge {
            font-size: 11px;
            font-family: monospace;
            background: rgba(100,120,255,0.15);
            border: 1px solid rgba(100,120,255,0.3);
            color: #9ab;
            padding: 3px 8px;
            border-radius: 10px;
        }

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 270px;
            background: #141420;
            border-right: 1px solid #2a2a40;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .sidebar-top {
            padding: 14px;
            border-bottom: 1px solid #2a2a40;
        }
        .sidebar-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #556;
            margin-bottom: 10px;
        }

        .upload-area {
            border: 1px dashed #334;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            color: #778;
            font-size: 13px;
        }
        .upload-area:hover { border-color: #556aaa; background: rgba(100,110,200,0.06); color: #aab; }
        .upload-area.dragover { border-color: #6677cc; background: rgba(100,110,200,0.12); }

        .doc-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .doc-list::-webkit-scrollbar { width: 4px; }
        .doc-list::-webkit-scrollbar-track { background: transparent; }
        .doc-list::-webkit-scrollbar-thumb { background: #334; border-radius: 2px; }

        .doc-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid #2a2a3a;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
        }
        .doc-name {
            font-size: 12px;
            color: #ccd;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 5px;
        }
        .doc-status {
            font-size: 11px;
            margin-bottom: 8px;
        }
        .status-ready    { color: #4caf7a; }
        .status-partial  { color: #f0a030; }
        .status-empty    { color: #f44; }

        .doc-actions { display: flex; gap: 5px; }

        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid #2a2a40;
        }

        /* ── Buttons ── */
        .btn {
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            padding: 5px 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: opacity 0.15s;
        }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-blue   { background: #3355aa; color: #dde; }
        .btn-blue:not(:disabled):hover   { background: #4466cc; }
        .btn-red    { background: #662233; color: #fcc; }
        .btn-red:not(:disabled):hover    { background: #882244; }
        .btn-ghost  { background: rgba(255,255,255,0.06); color: #99a; }
        .btn-ghost:not(:disabled):hover  { background: rgba(255,255,255,0.1); }
        .btn-full   { width: 100%; justify-content: center; }

        /* ── Chat ── */
        .chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: #0f0f1a;
        }
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .messages::-webkit-scrollbar { width: 5px; }
        .messages::-webkit-scrollbar-track { background: transparent; }
        .messages::-webkit-scrollbar-thumb { background: #2a2a40; border-radius: 3px; }

        .empty-state {
            margin: auto;
            text-align: center;
            color: #334;
            font-size: 14px;
            line-height: 1.8;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }

        /* ── Messages ── */
        .msg { display: flex; flex-direction: column; max-width: 820px; }
        .msg.user   { align-self: flex-end; align-items: flex-end; }
        .msg.assistant { align-self: flex-start; align-items: flex-start; }

        .bubble {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .msg.user .bubble {
            background: #1e3a6e;
            border-radius: 14px 14px 4px 14px;
            color: #d0e4ff;
        }
        .msg.assistant .bubble {
            background: #1a1a2e;
            border: 1px solid #2a2a40;
            border-radius: 4px 14px 14px 14px;
            color: #d8d8e8;
        }

        /* streaming cursor */
        .cursor {
            display: inline-block;
            width: 2px;
            height: 13px;
            background: #6677cc;
            border-radius: 1px;
            animation: blink 0.7s step-end infinite;
            vertical-align: text-bottom;
            margin-left: 1px;
        }
        @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }

        /* ── Debug details ── */
        .debug {
            margin-top: 6px;
            font-size: 11px;
        }
        details.timing summary {
            cursor: pointer;
            list-style: none;
            color: #445;
            font-family: monospace;
            padding: 2px 0;
            user-select: none;
        }
        details.timing summary::before { content: '▶ '; }
        details.timing[open] summary::before { content: '▼ '; }
        details.timing summary:hover { color: #667; }

        .source-list { margin-top: 8px; display: flex; flex-direction: column; gap: 6px; }
        .source-item {
            background: rgba(100,110,200,0.07);
            border: 1px solid #2a2a40;
            border-radius: 5px;
            padding: 8px 10px;
        }
        .source-meta { font-family: monospace; color: #556; margin-bottom: 4px; }
        .source-text {
            color: #778;
            font-size: 11px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Input bar ── */
        .input-bar {
            padding: 14px 20px;
            border-top: 1px solid #1e1e30;
            background: #141420;
            display: flex;
            gap: 10px;
        }
        .input-bar input {
            flex: 1;
            background: #1a1a2e;
            border: 1px solid #2a2a40;
            border-radius: 8px;
            color: #d8d8e8;
            font-size: 14px;
            padding: 11px 16px;
            outline: none;
            transition: border-color 0.15s;
        }
        .input-bar input::placeholder { color: #445; }
        .input-bar input:focus { border-color: #4455aa; }
        .input-bar input:disabled { opacity: 0.4; }
        .input-bar button {
            background: #3355aa;
            color: #dde;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 11px 20px;
            cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .input-bar button:hover:not(:disabled) { background: #4466cc; }
        .input-bar button:disabled { background: #223; color: #445; cursor: not-allowed; }

        /* ── Spinner ── */
        .spin {
            display: inline-block;
            width: 10px; height: 10px;
            border: 2px solid rgba(200,210,255,0.25);
            border-top-color: #aabf;
            border-radius: 50%;
            animation: spin 0.5s linear infinite;
            margin-right: 5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="header">
    <h1>RAG Chat</h1>
    <div class="badges">
        <span class="badge" id="badgeChat">…</span>
        <span class="badge" id="badgeEmbed">…</span>
    </div>
</div>

<div class="layout">

    {{-- ── Sidebar ── --}}
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-label">Documents</div>
            <label class="upload-area" id="uploadArea">
                <div>+ Upload PDF</div>
                <div style="font-size:10px;margin-top:3px;color:#445">click or drag &amp; drop</div>
                <input type="file" id="fileInput" accept=".pdf" style="display:none">
            </label>
        </div>

        <div class="doc-list" id="docList">
            <div style="text-align:center;color:#334;font-size:12px;padding:24px 0">No documents</div>
        </div>

        <div class="sidebar-footer">
            <button class="btn btn-red btn-full" id="clearBtn">Clear all data</button>
        </div>
    </div>

    {{-- ── Chat ── --}}
    <div class="chat">
        <div class="messages" id="messages">
            <div class="empty-state" id="emptyState">
                <div class="icon">🔍</div>
                Upload a PDF → Process → Ask
            </div>
        </div>

        <div class="input-bar">
            <input type="text" id="qInput" placeholder="Ask about your documents…" disabled>
            <button id="sendBtn" disabled>Send</button>
        </div>
    </div>
</div>

<script>
const CONFIG = {
    chat:  '{{ config("ollama.chat_model") }}',
    embed: '{{ config("ollama.embed_model") }}',
};
document.getElementById('badgeChat').textContent  = CONFIG.chat;
document.getElementById('badgeEmbed').textContent = CONFIG.embed;

// ── Helpers ──────────────────────────────────────────────────────────────────

function esc(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Document management ──────────────────────────────────────────────────────

async function loadDocs() {
    const res  = await fetch('/api/documents');
    const docs = await res.json();
    renderDocs(docs);

    const ready = docs.some(d => d.embedded_count > 0);
    document.getElementById('qInput').disabled  = !ready;
    document.getElementById('sendBtn').disabled = !ready;
}

function renderDocs(docs) {
    const list = document.getElementById('docList');
    if (!docs.length) {
        list.innerHTML = '<div style="text-align:center;color:#334;font-size:12px;padding:24px 0">No documents</div>';
        return;
    }
    list.innerHTML = docs.map(d => {
        let statusHtml;
        if (d.embedded_count > 0 && d.embedded_count >= d.chunks_count) {
            statusHtml = `<span class="status-ready">✓ ready (${d.chunks_count} chunks)</span>`;
        } else if (d.chunks_count > 0) {
            statusHtml = `<span class="status-partial">⚠ ${d.embedded_count}/${d.chunks_count} embedded</span>`;
        } else {
            statusHtml = `<span class="status-empty">○ not processed</span>`;
        }
        const btnLabel = d.embedded_count > 0 ? 'Re-process' : 'Process';
        return `
            <div class="doc-item" data-id="${d.id}">
                <div class="doc-name" title="${esc(d.title)}">${esc(d.title)}</div>
                <div class="doc-status">${statusHtml}</div>
                <div class="doc-actions">
                    <button class="btn btn-blue" onclick="processDoc(${d.id},this)">${btnLabel}</button>
                    <button class="btn btn-ghost" onclick="deleteDoc(${d.id})">✕</button>
                </div>
            </div>`;
    }).join('');
}

// Upload — triggered by file input or drag-and-drop
const uploadArea = document.getElementById('uploadArea');
const fileInput  = document.getElementById('fileInput');

uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', ()  => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) uploadFile(file);
});
fileInput.addEventListener('change', e => {
    if (e.target.files[0]) uploadFile(e.target.files[0]);
    e.target.value = '';
});

async function uploadFile(file) {
    uploadArea.innerHTML = '<div><span class="spin"></span>Uploading…</div>';

    const form = new FormData();
    form.append('file', file);

    try {
        const res = await fetch('/api/documents', { method: 'POST', body: form });
        const doc = await res.json();

        if (!res.ok) {
            alert('Upload failed: ' + (doc.message || res.status));
            return;
        }

        await loadDocs();

        // Auto-process after upload
        const btn = document.querySelector(`[data-id="${doc.id}"] .btn-blue`);
        if (btn) processDoc(doc.id, btn);
    } catch (err) {
        alert('Upload error: ' + err.message);
    } finally {
        uploadArea.innerHTML = `
            <div>+ Upload PDF</div>
            <div style="font-size:10px;margin-top:3px;color:#445">click or drag &amp; drop</div>`;
    }
}

async function processDoc(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span>Processing…';

    try {
        const res  = await fetch(`/api/documents/${id}/process`);
        const data = await res.json();
        if (!res.ok) {
            alert('Processing failed: ' + (data.error || res.status));
            return;
        }
        await loadDocs();
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
    }
}

async function deleteDoc(id) {
    if (!confirm('Delete this document?')) return;
    const res = await fetch(`/api/documents/${id}`, { method: 'DELETE' });
    if (res.ok) await loadDocs();
    else alert('Delete failed');
}

document.getElementById('clearBtn').addEventListener('click', async () => {
    if (!confirm('Delete ALL documents and clear the knowledge base?')) return;
    const res = await fetch('/api/documents', { method: 'DELETE' });
    if (!res.ok) { alert('Clear failed'); return; }
    await loadDocs();
    const msgs = document.getElementById('messages');
    msgs.innerHTML = `
        <div class="empty-state" id="emptyState">
            <div class="icon">🔍</div>
            Upload a PDF → Process → Ask
        </div>`;
});

// ── Chat / streaming ─────────────────────────────────────────────────────────

const messagesEl = document.getElementById('messages');
const qInput     = document.getElementById('qInput');
const sendBtn    = document.getElementById('sendBtn');

function removeEmptyState() {
    document.getElementById('emptyState')?.remove();
}

function appendUserMsg(text) {
    removeEmptyState();
    const el = document.createElement('div');
    el.className = 'msg user';
    el.innerHTML = `<div class="bubble">${esc(text)}</div>`;
    messagesEl.appendChild(el);
    messagesEl.scrollTop = messagesEl.scrollHeight;
}

function createAssistantMsg() {
    removeEmptyState();

    const wrapper = document.createElement('div');
    wrapper.className = 'msg assistant';

    const bubble = document.createElement('div');
    bubble.className = 'bubble';

    const textSpan = document.createElement('span');
    const cursor   = document.createElement('span');
    cursor.className = 'cursor';

    bubble.appendChild(textSpan);
    bubble.appendChild(cursor);
    wrapper.appendChild(bubble);
    messagesEl.appendChild(wrapper);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    let fullText = '';

    return {
        addToken(t) {
            fullText += t;
            textSpan.textContent = fullText;
            messagesEl.scrollTop = messagesEl.scrollHeight;
        },
        setError(msg) {
            cursor.remove();
            textSpan.textContent = '⚠ ' + msg;
            textSpan.style.color = '#f66';
        },
        finalize(sources, timingMs) {
            cursor.remove();

            if (!sources || !sources.length) return;

            const debugEl  = document.createElement('div');
            debugEl.className = 'debug';

            const details  = document.createElement('details');
            details.className = 'timing';

            const summary  = document.createElement('summary');
            if (timingMs) {
                const t = timingMs;
                summary.textContent =
                    `embed ${t.embed ?? '?'}ms  ·  retrieval ${t.retrieval ?? '?'}ms  ·  generate ${t.generate ?? '?'}ms  ·  total ${t.total ?? '?'}ms`;
            } else {
                summary.textContent = 'sources';
            }

            const sourceList = document.createElement('div');
            sourceList.className = 'source-list';

            sources.forEach(s => {
                const item = document.createElement('div');
                item.className = 'source-item';
                item.innerHTML = `
                    <div class="source-meta">chunk ${s.chunk_index} &nbsp;·&nbsp; dist ${Number(s.distance).toFixed(4)} &nbsp;·&nbsp; doc #${s.document_id}</div>
                    <div class="source-text">${esc(s.content)}</div>`;
                sourceList.appendChild(item);
            });

            details.appendChild(summary);
            details.appendChild(sourceList);
            debugEl.appendChild(details);
            bubble.appendChild(debugEl);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        },
    };
}

async function send() {
    const question = qInput.value.trim();
    if (!question || sendBtn.disabled) return;

    appendUserMsg(question);
    qInput.value    = '';
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spin"></span>';

    const msg = createAssistantMsg();
    let sources           = null;
    let sourceTiming      = {};

    try {
        const res = await fetch('/api/ask', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/x-ndjson' },
            body:    JSON.stringify({ question }),
        });

        if (!res.ok) {
            const d = await res.json().catch(() => ({}));
            msg.setError(d.error || `HTTP ${res.status}`);
            return;
        }

        const reader  = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer    = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop(); // keep incomplete line

            for (const line of lines) {
                if (!line.trim()) continue;
                let event;
                try { event = JSON.parse(line); } catch { continue; }

                switch (event.type) {
                    case 'sources':
                        sources      = event.sources;
                        sourceTiming = event.timing_ms || {};
                        break;
                    case 'token':
                        msg.addToken(event.token);
                        break;
                    case 'done':
                        msg.finalize(sources, { ...sourceTiming, ...event.timing_ms });
                        break;
                    case 'error':
                        msg.setError(event.error);
                        break;
                }
            }
        }
    } catch (err) {
        msg.setError(err.message);
    } finally {
        sendBtn.disabled  = false;
        sendBtn.textContent = 'Send';
        qInput.focus();
    }
}

sendBtn.addEventListener('click', send);
qInput.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });

// ── Init ─────────────────────────────────────────────────────────────────────
loadDocs();
</script>
</body>
</html>
