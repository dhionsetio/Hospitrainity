<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Menampilkan halaman formulir registrasi.
     *
     * The institution ("instansi") options are pulled from the distinct values
     * already present in the users table, so registrants can only join a real,
     * existing institution (previously the form hard-coded "Instansi A/B/C",
     * none of which matched the seeded institutions and broke supervisor scoping).
     */
    public function showRegistrationForm(): View
    {
        $institutions = User::query()
            ->whereNotNull('instansi')
            ->select('instansi')
            ->distinct()
            ->orderBy('instansi')
            ->pluck('instansi');

        return view('register', compact('institutions'));
    }

    /**
     * Menangani permintaan registrasi.
     */
    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);

        // 1. Validasi data input. instansi must match an existing institution.
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', Rule::exists('users', 'instansi')],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'terms' => ['accepted'],
        ]);

        // 2. Buat user baru (role defaults to 'user' at the database level;
        //    role is intentionally NOT accepted from the request to prevent
        //    privilege escalation).
        $user = User::create([
            'name' => $request->name,
            'instansi' => $request->instansi,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 3. Kirim email verifikasi (Registered event triggers the notification)
        event(new Registered($user));

        // 4. Login user yang baru dibuat
        Auth::login($user);

        // 5. Arahkan ke halaman "verifikasi email"; setelah terverifikasi
        //    pengguna diarahkan ke dashboard oleh middleware 'verified'.
        return redirect()->route('verification.notice');
    }
}
