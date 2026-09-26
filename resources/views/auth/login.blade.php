<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Academic</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background-color: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .auth-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 36px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e2e8f0;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #4f46e5);
            color: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }
        .brand-title { font-size: 18px; font-weight: 700; color: #1e293b; }
        .brand-subtitle { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .header-text { margin-bottom: 24px; }
        .header-text h2 { font-size: 22px; color: #0f172a; margin-bottom: 6px; }
        .header-text p { font-size: 14px; color: #64748b; }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        .form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            color: #ffffff;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover { opacity: 0.95; }

        .footer-text { text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
        .footer-text a { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .footer-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="brand">
            <div class="brand-logo">R</div>
            <div>
                <div class="brand-title">Student Academic</div>
                <div class="brand-subtitle">Academic Portal</div>
            </div>
        </div>

        <div class="header-text">
            <h2>Selamat Datang Kembali!</h2>
            <p>Masukkan akun kamu untuk masuk ke portal akademik.</p>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input 
                    type="email" 
                    id="email"
                    name="email" 
                    class="form-control" 
                    value="{{ old('email') }}" 
                    pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                    required>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    class="form-control" 
                    required>
            </div>

            <button type="submit" class="btn-submit">Masuk ke Portal</button>
        </form>

        <div class="footer-text">
            Belum punya akun? <a href="{{ route('register') }}">Daftar Sekarang</a>
        </div>
    </div>

</body>
</html>