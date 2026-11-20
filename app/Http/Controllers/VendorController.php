<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VendorController extends Controller
{
    private function input(Request $request): array
    {
        return $request->validate([
            'name' => ['required','string','max:255'], 'category' => ['required','string','max:100'],
            'bank' => ['required','string','max:100'], 'account_no' => ['required','regex:/^\d{6,20}$/'],
            'account_name' => ['required','string','max:255'],
        ]);
    }

    private function audit(Request $request, string $action, ?int $entityId, string $detail = ''): void
    {
        DB::table('audit_log')->insert(['at' => now(), 'user_id' => $request->user()->id, 'username' => $request->user()->username, 'action' => $action, 'entity' => 'vendor', 'entity_id' => $entityId, 'detail' => $detail, 'ip' => $request->ip()]);
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $rows = DB::table('vendors as v')->leftJoin('users as c', 'c.id', '=', 'v.created_by')->leftJoin('users as r', 'r.id', '=', 'v.reviewed_by')
            ->select('v.*', 'c.full_name as created_name', 'r.full_name as reviewed_name')->when($status, fn ($q) => $q->where('v.status', $status))->orderByDesc('v.id')->get();
        $pending = $request->user()->role === 'checker' ? $rows->where('status', 'PENDING') : collect();
        return view('vendors.index', compact('rows', 'pending', 'status'));
    }

    public function create(): View { return view('vendors.form', $this->formData()); }

    public function show(int $id): View
    {
        $vendor = DB::table('vendors as v')->join('users as c', 'c.id', '=', 'v.created_by')->leftJoin('users as r', 'r.id', '=', 'v.reviewed_by')->select('v.*', 'c.full_name as created_name', 'r.full_name as reviewed_name')->where('v.id', $id)->first();
        abort_unless($vendor, 404, 'Rekening tidak ditemukan.');
        $changes = DB::table('vendor_changes as vc')->join('users as req', 'req.id', '=', 'vc.requested_by')->leftJoin('users as rev', 'rev.id', '=', 'vc.reviewed_by')->select('vc.*', 'req.full_name as req_name', 'rev.full_name as rev_name')->where('vc.vendor_id', $id)->orderByDesc('vc.id')->get();
        return view('vendors.form', [...$this->formData(), 'vendor' => $vendor, 'changes' => $changes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->input($request);
        $exists = DB::table('vendors')->where('bank', $data['bank'])->where('account_no', $data['account_no'])->whereIn('status', ['PENDING','ACTIVE'])->exists();
        if ($exists) return back()->withInput()->withErrors(['account_no' => 'Rekening ini sudah terdaftar atau sedang menunggu persetujuan.']);
        $id = DB::table('vendors')->insertGetId([...$data, 'status' => 'PENDING', 'created_by' => $request->user()->id, 'created_at' => now()]);
        $this->audit($request, 'REKENING_AJUKAN', $id, $data['bank'].' '.$data['account_no']);
        return redirect()->route('vendors.index')->with('ok', 'Rekening diajukan ke Checker.');
    }

    public function review(Request $request, int $id): RedirectResponse
    {
        $request->validate(['action' => ['required','in:setujui,tolak'], 'note' => ['nullable','string','max:2000']]);
        $vendor = DB::table('vendors')->where('id', $id)->where('status', 'PENDING')->first(); abort_unless($vendor, 404);
        abort_if($vendor->created_by === $request->user()->id, 403, 'Anda tidak dapat menyetujui rekening yang Anda ajukan sendiri.');
        $approved = $request->action === 'setujui';
        if (!$approved && blank($request->note)) return back()->withErrors(['note' => 'Catatan wajib diisi bila menolak.']);
        DB::table('vendors')->where('id', $id)->update(['status' => $approved ? 'ACTIVE' : 'REJECTED', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->note]);
        $this->audit($request, $approved ? 'REKENING_SETUJU' : 'REKENING_TOLAK', $id, $request->note ?? '');
        return back()->with('ok', $approved ? 'Rekening disetujui dan sudah bisa dipakai.' : 'Rekening ditolak.');
    }

    public function requestChange(Request $request, int $id): RedirectResponse
    {
        $vendor = DB::table('vendors')->where('id', $id)->where('status', 'ACTIVE')->first(); abort_unless($vendor, 409, 'Hanya rekening aktif yang dapat diubah.');
        $data = $this->input($request); $reason = trim((string) $request->validate(['reason' => ['required','string','min:5','max:300']])['reason']);
        if (DB::table('vendor_changes')->where('vendor_id', $id)->where('status', 'PENDING')->exists()) return back()->withErrors(['reason' => 'Masih ada perubahan yang menunggu persetujuan Checker.']);
        DB::table('vendor_changes')->insert([...$data, 'vendor_id' => $id, 'reason' => $reason, 'status' => 'PENDING', 'requested_by' => $request->user()->id, 'requested_at' => now()]);
        $this->audit($request, 'REKENING_UBAH_AJUKAN', $id, $vendor->bank.' '.$vendor->account_no.' -> '.$data['bank'].' '.$data['account_no']);
        return back()->with('ok', 'Perubahan rekening diajukan ke Checker.');
    }

    public function reviewChange(Request $request, int $changeId): RedirectResponse
    {
        $request->validate(['action' => ['required','in:setujui,tolak'], 'note' => ['nullable','string','max:2000']]);
        $change = DB::table('vendor_changes')->where('id', $changeId)->where('status', 'PENDING')->first(); abort_unless($change, 404);
        abort_if($change->requested_by === $request->user()->id, 403, 'Anda tidak dapat menyetujui perubahan sendiri.');
        $approved = $request->action === 'setujui'; if (!$approved && blank($request->note)) return back()->withErrors(['note' => 'Catatan wajib diisi bila menolak.']);
        DB::transaction(function () use ($change, $request, $approved) { DB::table('vendor_changes')->where('id', $change->id)->update(['status' => $approved ? 'APPROVED' : 'REJECTED', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->note]); if ($approved) DB::table('vendors')->where('id', $change->vendor_id)->update(['name' => $change->name, 'bank' => $change->bank, 'account_no' => $change->account_no, 'account_name' => $change->account_name]); });
        $this->audit($request, $approved ? 'REKENING_UBAH_SETUJU' : 'REKENING_UBAH_TOLAK', $change->vendor_id, $request->note ?? '');
        return back()->with('ok', $approved ? 'Perubahan rekening disetujui.' : 'Perubahan rekening ditolak.');
    }

    public function deactivate(Request $request, int $id): RedirectResponse { DB::table('vendors')->where('id', $id)->where('status', 'ACTIVE')->update(['status' => 'INACTIVE']); $this->audit($request, 'REKENING_NONAKTIF', $id); return redirect()->route('vendors.index')->with('ok', 'Rekening dinonaktifkan.'); }
    private function formData(): array { return ['vendor' => null, 'changes' => collect(), 'banks' => ['BNI','BRI','Mandiri','BCA','BTN','Bank Syariah Indonesia','CIMB Niaga','Lainnya'], 'categories' => ['Vendor','Karyawan','Rekening sendiri','Lainnya']]; }
}
