<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ask Test</title>
    <style>
        body { font-family: monospace; max-width: 900px; margin: 40px auto; padding: 0 20px; }
        input { width: 500px; padding: 8px; font-size: 14px; }
        button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        #result { margin-top: 20px; white-space: pre-wrap; background: #f5f5f5; padding: 16px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Ask Test</h1>

    <div>
        <input type="text" id="question" placeholder="Ask something…">
        <button id="askBtn">Ask</button>
    </div>

    <pre id="result"></pre>

    <script>
        document.getElementById('askBtn').addEventListener('click', async () => {
            const question = document.getElementById('question').value.trim();
            if (!question) return;

            document.getElementById('result').textContent = 'Thinking…';

            const response = await fetch('/api/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ question })
            });

            const data = await response.json();
            document.getElementById('result').textContent = JSON.stringify(data, null, 2);
        });

        document.getElementById('question').addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('askBtn').click();
        });
    </script>
</body>
</html>
