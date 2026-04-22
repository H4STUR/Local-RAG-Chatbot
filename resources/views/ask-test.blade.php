<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Ask Test — Streaming Debug</title>
    <style>
        body { font-family: monospace; background: #0f0f18; color: #bbc; padding: 30px; }
        h1   { color: #9ab; margin-bottom: 20px; font-size: 18px; }
        .row { display: flex; gap: 8px; margin-bottom: 16px; }
        input {
            flex: 1; padding: 10px 14px;
            background: #161620; border: 1px solid #2a2a40; border-radius: 6px;
            color: #dde; font-size: 14px; outline: none;
        }
        input:focus { border-color: #4455aa; }
        button {
            padding: 10px 18px; background: #3355aa; color: #dde;
            border: none; border-radius: 6px; cursor: pointer; font-size: 14px;
        }
        button:hover { background: #4466cc; }
        button:disabled { background: #223; color: #445; cursor: not-allowed; }
        #log {
            background: #111118; border: 1px solid #222230; border-radius: 6px;
            padding: 16px; min-height: 200px; white-space: pre-wrap;
            font-size: 12px; line-height: 1.7; color: #99a;
        }
        .ev-sources  { color: #6688cc; }
        .ev-token    { color: #88cc88; }
        .ev-done     { color: #ccaa55; }
        .ev-error    { color: #ff6655; }
        .ev-answer   { color: #dde; margin: 8px 0; border-left: 2px solid #4466cc; padding-left: 8px; }
        hr { border: none; border-top: 1px solid #222; margin: 8px 0; }
    </style>
</head>
<body>
    <h1>Ask Test — streaming debug</h1>

    <div class="row">
        <input type="text" id="q" placeholder="Ask something…">
        <button id="btn">Ask</button>
    </div>

    <div id="log">Ready. Enter a question and click Ask.</div>

    <script>
        const qEl  = document.getElementById('q');
        const btn  = document.getElementById('btn');
        const log  = document.getElementById('log');

        async function ask() {
            const question = qEl.value.trim();
            if (!question) return;

            btn.disabled = true;
            log.innerHTML = `<span style="color:#556">▶ POST /api/ask — "${question}"</span>\n<hr>`;
            let answerBuf = '';

            try {
                const res = await fetch('/api/ask', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/x-ndjson' },
                    body:    JSON.stringify({ question }),
                });

                if (!res.ok) {
                    const d = await res.json().catch(() => ({}));
                    log.innerHTML += `<span class="ev-error">HTTP ${res.status}: ${JSON.stringify(d)}</span>`;
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
                    buffer = lines.pop();

                    for (const line of lines) {
                        if (!line.trim()) continue;
                        let ev;
                        try { ev = JSON.parse(line); } catch { continue; }

                        if (ev.type === 'sources') {
                            const t = ev.timing_ms || {};
                            log.innerHTML += `<span class="ev-sources">sources  embed=${t.embed}ms  retrieval=${t.retrieval}ms  (${ev.sources.length} chunks)</span>\n`;
                            ev.sources.forEach((s, i) => {
                                log.innerHTML += `  <span style="color:#445">[${i+1}] chunk ${s.chunk_index}  dist ${Number(s.distance).toFixed(4)}  doc#${s.document_id}</span>\n`;
                            });
                            log.innerHTML += '<hr>';
                        } else if (ev.type === 'token') {
                            answerBuf += ev.token;
                        } else if (ev.type === 'done') {
                            const t = ev.timing_ms || {};
                            log.innerHTML += `<span class="ev-answer">${answerBuf}</span>\n<hr>`;
                            log.innerHTML += `<span class="ev-done">done  generate=${t.generate}ms  total=${t.total}ms</span>\n`;
                        } else if (ev.type === 'error') {
                            log.innerHTML += `<span class="ev-error">error: ${ev.error}</span>\n`;
                        }

                        log.scrollTop = log.scrollHeight;
                    }
                }
            } catch (err) {
                log.innerHTML += `<span class="ev-error">fetch error: ${err.message}</span>`;
            } finally {
                btn.disabled = false;
                qEl.focus();
            }
        }

        btn.addEventListener('click', ask);
        qEl.addEventListener('keydown', e => { if (e.key === 'Enter') ask(); });
    </script>
</body>
</html>
