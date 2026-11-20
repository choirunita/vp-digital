<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VpController extends Controller
{
    private function vpForMaker(Request $request, int $id): object
    {
        $vp = DB::table('vp')->where('id', $id)->where('maker_id', $request->user()->id)->first();
        abort_unless($vp, 404, 'VP tidak ditemukan.'); return $vp;
    }
    private function audit(Request $request, string $action, int $id, string $detail = ''): void
    {
        DB::table('audit_log')->insert(['at' => now(), 'user_id' => $request->user()->id, 'username' => $request->user()->username, 'action' => $action, 'entity' => 'vp', 'entity_id' => $id, 'detail' => $detail, 'ip' => $request->ip()]);
    }
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $month = (string) $request->query('bulan', '');
        $search = trim((string) $request->query('q', ''));
        $rows = DB::table('vp as v')->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')->select('v.*', 'p.bank as pcs_bank')
            ->selectSub(DB::table('vp_lines')->selectRaw('COUNT(*)')->whereColumn('vp_lines.vp_id', 'v.id'), 'n_lines')
            ->selectSub(DB::table('vp_lines')->select('name')->whereColumn('vp_lines.vp_id', 'v.id')->orderBy('id')->limit(1), 'first_name')
            ->when($request->user()->role === 'maker', fn ($q) => $q->where('v.maker_id', $request->user()->id))
            ->when($status !== '', fn ($q) => $q->where('v.status', $status))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, fn ($q) => $q->whereRaw("DATE_FORMAT(COALESCE(v.pay_date, v.created_at), '%Y-%m') = ?", [$month]))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) { $like = '%'.$search.'%'; $sub->where('v.reg_no', 'like', $like)->orWhere('v.ref_no', 'like', $like)->orWhere('v.description', 'like', $like)->orWhereExists(fn ($lines) => $lines->selectRaw('1')->from('vp_lines as l')->whereColumn('l.vp_id', 'v.id')->where('l.name', 'like', $like)); }))
            ->orderByDesc('v.id')->get();
        return view('vp.index', compact('rows', 'status', 'month', 'search'));
    }

    public function show(Request $request, int $id): View
    {
        $vp = DB::table('vp as v')->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')->join('users as maker', 'maker.id', '=', 'v.maker_id')->leftJoin('users as checker', 'checker.id', '=', 'v.checker_id')->leftJoin('users as approver', 'approver.id', '=', 'v.approver1_id')->leftJoin('users as printer', 'printer.id', '=', 'v.printed_by')->select('v.*', 'p.bank as pcs_bank', 'p.account_no as pcs_account_no', 'maker.full_name as maker_name', 'checker.full_name as checker_name', 'approver.full_name as approver_name', 'printer.full_name as printed_name')->where('v.id', $id)->first();
        abort_unless($vp, 404, 'VP tidak ditemukan.');
        abort_if($request->user()->role === 'maker' && $vp->maker_id !== $request->user()->id, 403, 'Anda hanya dapat melihat VP yang Anda buat.');
        $lines = DB::table('vp_lines')->where('vp_id', $id)->orderBy('id')->get();
        $history = DB::table('vp_history as h')->join('users as u', 'u.id', '=', 'h.user_id')->select('h.*', 'u.full_name', 'u.role')->where('h.vp_id', $id)->orderByDesc('h.id')->get();
        return view('vp.show', compact('vp', 'lines', 'history'));
    }
    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required','in:SINGLE,MASS']]);
        $id = DB::table('vp')->insertGetId(['status' => 'DRAFT', 'kind' => $data['kind'], 'pcs_account_id' => DB::table('pcs_accounts')->where('active', true)->value('id'), 'pay_date' => today(), 'maker_id' => $request->user()->id, 'created_at' => now()]);
        DB::table('vp_history')->insert(['vp_id' => $id, 'user_id' => $request->user()->id, 'action' => 'Draft dibuat', 'to_status' => 'DRAFT', 'note' => '', 'at' => now()]);
        $this->audit($request, 'VP_DRAFT', $id, $data['kind']); return redirect()->route('vp.edit', $id);
    }
    public function edit(Request $request, int $id): View
    {
        $vp = $this->vpForMaker($request, $id); abort_if(!in_array($vp->status, ['DRAFT','DIKEMBALIKAN']), 403, 'VP sudah tidak dapat diubah.');
        return view('vp.edit', ['vp' => $vp, 'lines' => DB::table('vp_lines')->where('vp_id', $id)->orderBy('id')->get(), 'vendors' => DB::table('vendors')->where('status','ACTIVE')->orderBy('name')->get(), 'pcsAccounts' => DB::table('pcs_accounts')->where('active', true)->orderBy('bank')->get()]);
    }
    public function update(Request $request, int $id): RedirectResponse
    {
        $vp = $this->vpForMaker($request, $id); abort_if(!in_array($vp->status, ['DRAFT','DIKEMBALIKAN']), 403);
        $data = $request->validate(['kind' => ['required','in:SINGLE,MASS'], 'pcs_account_id' => ['nullable','integer','exists:pcs_accounts,id'], 'ref_no' => ['nullable','string','max:255'], 'pay_date' => ['nullable','date'], 'description' => ['required','string','max:5000']]);
        DB::table('vp')->where('id', $id)->update($data); return back()->with('ok', 'Data VP disimpan.');
    }
    public function addLine(Request $request, int $id): RedirectResponse
    {
        $vp = $this->vpForMaker($request, $id); abort_if(!in_array($vp->status, ['DRAFT','DIKEMBALIKAN']), 403);
        $data = $request->validate(['vendor_id' => ['required','integer'], 'amount' => ['required','numeric','min:1'], 'note' => ['nullable','string','max:1000']]);
        $vendor = DB::table('vendors')->where('id', $data['vendor_id'])->where('status', 'ACTIVE')->first();
        if (!$vendor) return back()->withErrors(['vendor_id' => 'Pilih rekening penerima aktif.']);
        if ($vp->kind === 'SINGLE') DB::table('vp_lines')->where('vp_id', $id)->delete();
        DB::table('vp_lines')->insert(['vp_id' => $id, 'vendor_id' => $vendor->id, 'name' => $vendor->name, 'bank' => $vendor->bank, 'account_no' => $vendor->account_no, 'account_name' => $vendor->account_name, 'amount' => $data['amount'], 'note' => $data['note'] ?? '']);
        DB::table('vp')->where('id', $id)->update(['total' => DB::table('vp_lines')->where('vp_id', $id)->sum('amount')]);
        return back()->with('ok', 'Penerima ditambahkan.');
    }
    public function removeLine(Request $request, int $id, int $lineId): RedirectResponse
    {
        $this->vpForMaker($request, $id); DB::table('vp_lines')->where('id', $lineId)->where('vp_id', $id)->delete(); DB::table('vp')->where('id', $id)->update(['total' => DB::table('vp_lines')->where('vp_id', $id)->sum('amount')]); return back()->with('ok', 'Penerima dihapus.');
    }
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $vp = $this->vpForMaker($request, $id);
        abort_if(!in_array($vp->status, ['DRAFT','DIKEMBALIKAN']), 403);
        DB::transaction(function () use ($id) {
            DB::table('vp_history')->where('vp_id', $id)->delete();
            DB::table('vp_lines')->where('vp_id', $id)->delete();
            DB::table('audit_log')->where('entity', 'vp')->where('entity_id', $id)->delete();
            DB::table('vp')->where('id', $id)->delete();
        });
        return redirect()->route('vp.index')->with('ok', 'Draft VP berhasil dihapus.');
    }
    public function submit(Request $request, int $id): RedirectResponse
    {
        $vp = $this->vpForMaker($request, $id); abort_if(!in_array($vp->status, ['DRAFT','DIKEMBALIKAN']), 403);
        if (!$vp->pcs_account_id || blank($vp->description) || !DB::table('vp_lines')->where('vp_id', $id)->exists()) return back()->withErrors(['submit' => 'Lengkapi rekening PCS, uraian, dan minimal satu penerima sebelum mengajukan.']);
        DB::transaction(function () use ($request, $id, $vp) {
            $year = now()->year; $month = now()->month;
            DB::table('vp_seq')->updateOrInsert(['year' => $year, 'month' => $month], ['last' => 0]);
            $seq = DB::table('vp_seq')->where(['year' => $year, 'month' => $month])->lockForUpdate()->first(); $number = $seq->last + 1;
            DB::table('vp_seq')->where(['year' => $year, 'month' => $month])->update(['last' => $number]);
            $reg = $vp->reg_no ?: sprintf('VP/%d/%02d/%04d', $year, $month, $number);
            DB::table('vp')->where('id', $id)->update(['reg_no' => $reg, 'status' => 'DIAJUKAN', 'submitted_at' => now(), 'revision' => $vp->revision + 1]);
            DB::table('vp_history')->insert(['vp_id' => $id, 'user_id' => $request->user()->id, 'action' => 'Diajukan', 'from_status' => $vp->status, 'to_status' => 'DIAJUKAN', 'note' => '', 'at' => now()]);
        });
        $this->audit($request, 'VP_AJUKAN', $id); return redirect()->route('vp.index')->with('ok', 'VP berhasil diajukan ke Checker.');
    }
}
