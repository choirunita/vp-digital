<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View { return view('profile.index', ['user' => $request->user()]); }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['old_password' => ['required','string'], 'password' => ['required','string','min:8','confirmed','regex:/[A-Za-z]/','regex:/\d/']]);
        if (!Hash::check($data['old_password'], $request->user()->password)) return back()->withErrors(['old_password' => 'Kata sandi lama salah.']);
        DB::table('users')->where('id', $request->user()->id)->update(['password' => Hash::make($data['password']), 'must_change_pw' => false, 'updated_at' => now()]);
        $this->audit($request, 'KATA_SANDI_GANTI', '');
        return back()->with('ok', 'Kata sandi diganti.');
    }

    public function signature(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['maker','checker','approver1'], true), 403);
        $request->validate(['signature' => ['required','image','mimes:png,jpg,jpeg','max:2048']]);
        $ext = $request->file('signature')->extension() === 'png' ? 'png' : 'jpg';
        $name = 'ttd-'.$request->user()->id.'-'.now()->format('YmdHis').'.'.$ext;
        if ($request->user()->signature_path) Storage::disk('local')->delete('signatures/'.$request->user()->signature_path);
        $request->file('signature')->storeAs('signatures', $name, 'local');
        DB::table('users')->where('id', $request->user()->id)->update(['signature_path' => $name, 'updated_at' => now()]);
        $this->audit($request, 'TTD_UNGGAH', $name);
        return back()->with('ok', 'Pindaian tanda tangan disimpan.');
    }

    public function showSignature(Request $request, int $id)
    {
        $user = DB::table('users')->find($id);
        abort_unless($user && $user->signature_path && ($request->user()->id === $id || $request->user()->role === 'admin'), 404);
        abort_unless(Storage::disk('local')->exists('signatures/'.$user->signature_path), 404);
        return Storage::disk('local')->response('signatures/'.$user->signature_path);
    }

    private function audit(Request $request, string $action, string $detail): void
    {
        DB::table('audit_log')->insert(['at' => now(), 'user_id' => $request->user()->id, 'username' => $request->user()->username, 'action' => $action, 'entity' => 'user', 'entity_id' => $request->user()->id, 'detail' => $detail, 'ip' => $request->ip()]);
    }
}
