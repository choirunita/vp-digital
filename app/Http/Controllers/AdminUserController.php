<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private const ROLES = ['maker', 'checker', 'approver1', 'approver2', 'sesdir', 'admin', 'guest'];

    public function index(Request $request): View
    {
        return view('admin-users.index', ['rows' => DB::table('users')->orderBy('role')->orderBy('full_name')->get(), 'roles' => self::ROLES, 'tempPassword' => $request->session()->pull('temp_password')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['username' => ['required', 'regex:/^[a-z0-9._-]{3,30}$/', 'unique:users,username'], 'full_name' => ['required','string','max:255'], 'title' => ['nullable','string','max:255'], 'role' => ['required','in:maker,checker,approver1,approver2,sesdir,admin,guest']]);
        $password = $this->temporaryPassword();
        $id = DB::table('users')->insertGetId(['username' => strtolower($data['username']), 'password' => Hash::make($password), 'full_name' => $data['full_name'], 'title' => $data['title'] ?? '', 'role' => $data['role'], 'active' => true, 'must_change_pw' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'PENGGUNA_TAMBAH', $id, $data['username'].' ('.$data['role'].')');
        $request->session()->flash('temp_password', ['username' => $data['username'], 'password' => $password]);
        return redirect()->route('admin.users.index');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = DB::table('users')->find($id); abort_unless($user, 404, 'Pengguna tidak ditemukan.');
        if ($request->input('action') === 'reset') {
            $password = $this->temporaryPassword();
            DB::table('users')->where('id', $id)->update(['password' => Hash::make($password), 'must_change_pw' => true, 'updated_at' => now()]);
            $this->audit($request, 'PENGGUNA_RESET_SANDI', $id, $user->username);
            $request->session()->flash('temp_password', ['username' => $user->username, 'password' => $password]);
            return redirect()->route('admin.users.index');
        }
        $data = $request->validate(['full_name' => ['required','string','max:255'], 'title' => ['nullable','string','max:255'], 'role' => ['required','in:maker,checker,approver1,approver2,sesdir,admin,guest'], 'active' => ['required','boolean']]);
        if ($id === $request->user()->id && (!$data['active'] || $data['role'] !== 'admin')) return back()->withErrors(['full_name' => 'Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri.']);
        DB::table('users')->where('id', $id)->update([...$data, 'title' => $data['title'] ?? '', 'updated_at' => now()]);
        $this->audit($request, 'PENGGUNA_UBAH', $id, $user->username.': peran '.$user->role.'->'.$data['role'].', aktif '.(int) $data['active']);
        return back()->with('ok', 'Data '.$user->username.' disimpan.');
    }

    private function temporaryPassword(): string { return 'Pcs-'.Str::lower(Str::random(8)).'9'; }
    private function audit(Request $request, string $action, int $entityId, string $detail): void
    {
        DB::table('audit_log')->insert(['at' => now(), 'user_id' => $request->user()->id, 'username' => $request->user()->username, 'action' => $action, 'entity' => 'user', 'entity_id' => $entityId, 'detail' => $detail, 'ip' => $request->ip()]);
    }
}
