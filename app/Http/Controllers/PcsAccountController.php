<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PcsAccountController extends Controller
{
    public function index(): View
    {
        return view('pcs-accounts.index', [
            'rows' => DB::table('pcs_accounts')->orderBy('bank')->get(),
            'banks' => ['BNI', 'BRI', 'Mandiri', 'BCA', 'BTN', 'Bank Syariah Indonesia', 'CIMB Niaga', 'Lainnya'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['bank' => ['required','string','max:100'], 'account_no' => ['required','regex:/^\d{6,20}$/'], 'account_name' => ['required','string','max:255']]);
        DB::table('pcs_accounts')->insert([...$data, 'active' => true]);
        return back()->with('ok', 'Rekening bank perusahaan ditambahkan.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $row = DB::table('pcs_accounts')->find($id);
        abort_unless($row, 404);
        DB::table('pcs_accounts')->where('id', $id)->update(['active' => !$row->active]);
        return back()->with('ok', 'Status rekening diperbarui.');
    }
}
