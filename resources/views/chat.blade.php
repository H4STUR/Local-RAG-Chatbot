<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Local RAG Chat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .chat-box {
            border: 1px solid #ccc;
            padding: 16px;
            min-height: 300px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }

        .message {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 8px;
        }

        .user      { background: #e8f0fe; }
        .assistant { background: #eef7ee; }

        .sources {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .timing {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
            font-family: monospace;
        }

        .row {
            display: flex;
            gap: 10px;
        }

        input[type="text"] {
            flex: 1;
            padding: 12px;
            font-size: 16px;
        }

        button {
            padding: 12px 18px;
            font-size: 16px;
            cursor: pointer;
        }

        .loading {
            color: #777;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>Local RAG Chat</h1>

    <div class="chat-box" id="chatBox"></div>

    <div class="row">
        <input type="text" id="question" placeholder="Ask something..." />
        <button id="sendBtn">Send</button>
    </div>

    <script>
        const chatBox = document.getElementById('chatBox');
        const questionInput = document.getElementById('question');
        const sendBtn = document.getElementById('sendBtn');

        function addMessage(type, text, data = {}) {
            const div = document.createElement('div');
            div.className = 'message ' + type;

            const content = document.createElement('div');
            content.textContent = text;
            div.appendChild(content);

            if (data.sources && data.sources.length) {
                const sourcesDiv = document.createElement('div');
                sourcesDiv.className = 'sources';
                sourcesDiv.textContent = 'Sources: ' + data.sources.map(
                    (s, i) => `#${i + 1} chunk ${s.chunk_index} (dist ${Number(s.distance).toFixed(4)})`
                ).join(' · ');
                div.appendChild(sourcesDiv);
            }

            if (data.timing_ms) {
                const t = data.timing_ms;
                const timingDiv = document.createElement('div');
                timingDiv.className = 'timing';
                timingDiv.textContent =
                    `embed ${t.embed}ms · retrieval ${t.retrieval}ms · generate ${t.generate}ms · total ${t.total}ms`;
                div.appendChild(timingDiv);
            }

            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        async function sendQuestion() {
            const question = questionInput.value.trim();
            if (!question) return;

            addMessage('user', question);
            questionInput.value = '';
            sendBtn.disabled = true;

            const loading = document.createElement('div');
            loading.className = 'message assistant loading';
            loading.textContent = 'Thinking…';
            chatBox.appendChild(loading);
            chatBox.scrollTop = chatBox.scrollHeight;

            try {
                const response = await fetch('/api/ask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ question })
                });

                const data = await response.json();
                loading.remove();

                if (!response.ok) {
                    addMessage('assistant', data.error || 'Something went wrong.');
                } else {
                    addMessage('assistant', data.answer || 'No answer returned.', data);
                }
            } catch (err) {
                loading.remove();
                addMessage('assistant', 'Request failed: ' + err.message);
            } finally {
                sendBtn.disabled = false;
            }
        }

        sendBtn.addEventListener('click', sendQuestion);
        questionInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendQuestion(); });
    </script>
</body>
</html>
