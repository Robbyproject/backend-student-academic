<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h2>Selamat Datang, {{ Auth::user()->name }}!</h2>
    <p>Email: {{ Auth::user()->email }}</p>
    <p>Role Anda: <strong>{{ strtoupper(Auth::user()->role) }}</strong></p>

    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>