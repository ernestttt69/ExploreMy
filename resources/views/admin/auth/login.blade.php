<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ExploreMY</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="simple-admin-login">
<main class="simple-login-card">
    <div class="simple-admin-brand"><img src="{{ asset('images/ExploreMy_icon.jpeg') }}" alt="ExploreMY"><div><strong>ExploreMY</strong><span>Admin</span></div></div>
    <h1>Admin login</h1>
    <p>Enter the administrator access code to continue.</p>
    @error('access_code')<div class="simple-login-error">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('admin.authenticate') }}">@csrf
        <label for="access_code">Access code</label>
        <div class="code-input"><input type="password" name="access_code" id="access_code" required autofocus autocomplete="current-password" maxlength="100"><button type="button" id="toggleCode" aria-label="Show access code">Show</button></div>
        <button type="submit" class="simple-login-submit">Enter admin</button>
    </form>
    <small>Authorised administrators only</small>
</main>
<script>const input=document.getElementById('access_code'),toggle=document.getElementById('toggleCode');toggle.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';toggle.textContent=show?'Hide':'Show';input.focus()});</script>
</body>
</html>
