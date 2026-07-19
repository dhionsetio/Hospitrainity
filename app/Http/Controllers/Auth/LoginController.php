<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleLandingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly RoleLandingResolver $landingResolver) {}

    /** Menampilkan halaman formulir login. */
    public function showLoginForm(): View
    {
        return view('login');
    }

    /** Menangani permintaan login. */
    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);

        // 1. Validasi data input dari form
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Coba untuk mengotentikasi pengguna
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Jika berhasil, regenerate session untuk keamanan
            $request->session()->regenerate();

            $user = Auth::user();

            // Stored intended URLs are deliberately ignored until they can be
            // proven same-origin and authorized for this account's role.
            return $this->landingResolver->redirect($user);
        }

        // 4. Jika otentikasi gagal
        // Kembalikan ke halaman login dengan pesan error
        return back()->withErrors([
            'email' => __('The email or password is incorrect.'),
        ])->onlyInput('email');
    }

    /** Menangani permintaan logout. */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login'); // Redirect ke halaman utama setelah logout
    }
}
