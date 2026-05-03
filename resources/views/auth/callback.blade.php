<!DOCTYPE html>
<html>
<head>
    <title>Authentication Callback</title>
    <script>
        window.opener.postMessage({
            token: '{{ $token }}',
            user: @json($user)
        }, '{{ env('FRONTEND_URL', 'http://localhost:3000') }}');
        window.close();
    </script>
</head>
<body>
    <p>Authentication successful. You can close this window.</p>
</body>
</html>
