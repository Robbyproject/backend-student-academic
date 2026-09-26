<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // 1. Form Register
    public function showRegister()
    {
        return view('auth.register');
    }

    // 2. Proses Register
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            
            'email'    => 'required|string|email:dns|unique:users,email',
            
            'password' => 'required|min:6',
            'role'     => 'required|in:admin,dosen,mahasiswa',
        ], [
            'email.email' => 'Format email harus valid dan memiliki domain lengkap (contoh: name@gmail.com).',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        return redirect()->route('login')->with('success', 'Registrasi berhasil! Silakan login.');
    }

    // 3. Form Login
    public function showLogin()
    {
        return view('auth.login');
    }

    // 4. Proses Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email', 'regex:/^[\w\.-]+@[\w\.-]+\.[a-zA-Z0-9]{2,}$/'],
            'password' => 'required',
        ], [
            'email.regex' => 'Format email tidak valid. Gunakan domain yang benar (contoh: user@gmail.com).',
            'email.email' => 'Format email tidak valid.',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Email atau password salah!'])->withInput($request->only('email'));
    }

    // 5. Proses Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}