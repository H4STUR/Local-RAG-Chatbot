<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ask Test</title>
</head>
<body>
    <h1>Ask Test</h1>

    <input type="text" id="question" placeholder="Ask something..." style="width: 400px;">
    <button id="askBtn">Ask</button>

    <pre id="result"></pre>

    <script>
        document.getElementById('askBtn').addEventListener('click', async () => {
            const question = document.getElementById('question').value;
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch('/ask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ question })
            });

            const data = await response.json();
            document.getElementById('result').textContent = JSON.stringify(data, null, 2);
        });
    </script>
</body>
</html>