<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        $key = strtolower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Terlalu banyak percobaan gagal. Tunggu 10 menit lalu coba lagi.']);
        }

        if (!Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password'], 'active' => true])) {
            RateLimiter::hit($key, 600);
            DB::table('audit_log')->insert([
                'username' => $credentials['username'], 'action' => 'LOGIN_GAGAL', 'entity' => 'user',
                'detail' => '', 'ip' => $request->ip(), 'at' => now(),
            ]);
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Nama pengguna atau kata sandi salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        DB::table('audit_log')->insert([
            'user_id' => Auth::id(), 'username' => Auth::user()->username, 'action' => 'LOGIN',
            'entity' => 'user', 'entity_id' => Auth::id(), 'detail' => '', 'ip' => $request->ip(), 'at' => now(),
        ]);
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            DB::table('audit_log')->insert([
                'user_id' => Auth::id(), 'username' => Auth::user()->username, 'action' => 'LOGOUT',
                'entity' => 'user', 'entity_id' => Auth::id(), 'detail' => '', 'ip' => $request->ip(), 'at' => now(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
