<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Upload PDF</title>
</head>
<body>
    <h1>Upload PDF</h1>
    <p>Or use <a href="/chat">/chat</a> — it has upload built-in.</p>

    <form method="POST" action="/api/documents" enctype="multipart/form-data" style="margin-top:16px">
        <input type="file" name="file" accept=".pdf" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
