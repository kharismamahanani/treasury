<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // ── Setup ────────────────────────────────────────────────────────────────

    public function setup(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $user = auth()->user();

        // Generate a fresh secret only if one doesn't exist yet
        $secret = $user->google2fa_secret
            ?? $this->google2fa->generateSecretKey();

        // Store the pending secret in session so we can confirm it before saving
        $request->session()->put('2fa_pending_secret', $secret);

        $qrCodeInline = $this->google2fa->getQRCodeInline(
            config('app.name'),
            $user->email ?? $user->username,
            $secret,
            200
        );

        return view('admin.2fa.setup', ['qrCodeInline' => $qrCodeInline, 'secret' => $secret]);
    }

    public function confirmSetup(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $secret = $request->session()->get('2fa_pending_secret');

        if (! $secret) {
            return back()->withErrors(['code' => 'Sesi setup sudah kedaluwarsa. Mulai ulang.']);
        }

        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'Kode tidak valid. Coba lagi.']);
        }

        auth()->user()->update(['google2fa_secret' => $secret]);
        $request->session()->forget('2fa_pending_secret');
        $request->session()->put('2fa_verified', true);

        return redirect('/')->with('success', '2FA berhasil diaktifkan untuk akun Anda.');
    }

    // ── Verify (challenge after login) ───────────────────────────────────────

    public function verify()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Sudah terverifikasi sesi ini — redirect ke tujuan
        if (session('2fa_verified')) {
            return redirect()->intended('/');
        }

        return view('admin.2fa.verify');
    }

    public function confirmVerify(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $secret = auth()->user()->google2fa_secret;

        // Window of 1 allows 30 seconds of clock drift in either direction
        $valid = $this->google2fa->verifyKey($secret, $request->code, 1);

        if (! $valid) {
            return back()->withErrors(['code' => 'Kode salah atau sudah kedaluwarsa. Coba kode baru.']);
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended('/');
    }
}
