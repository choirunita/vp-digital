<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->user()->role === 'checker' ? 'DIAJUKAN' : 'DIPERIKSA';
        $rows = DB::table('vp as v')->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')->join('users as maker', 'maker.id', '=', 'v.maker_id')->leftJoin('users as checker', 'checker.id', '=', 'v.checker_id')->select('v.*', 'p.bank as pcs_bank', 'maker.full_name as maker_name', 'checker.full_name as checker_name')->selectSub(DB::table('vp_lines')->selectRaw('COUNT(*)')->whereColumn('vp_lines.vp_id', 'v.id'), 'n_lines')->selectSub(DB::table('vp_lines')->select('name')->whereColumn('vp_lines.vp_id', 'v.id')->orderBy('id')->limit(1), 'first_name')->where('v.status', $status)->orderBy('v.submitted_at')->get();
        foreach ($rows as $row) if ($row->kind === 'MASS') $row->lines = DB::table('vp_lines')->where('vp_id', $row->id)->orderBy('id')->get();
        return view('approvals.index', compact('rows'));
    }

    public function act(Request $request, int $id, string $stage): RedirectResponse
    {
        abort_unless(in_array($stage, ['checker', 'approver1'], true) && $stage === $request->user()->role, 403);
        $request->validate(['action' => ['required', 'in:setujui,kembalikan,tolak'], 'note' => ['nullable', 'string', 'max:500']]);
        $vp = DB::table('vp')->find($id); abort_unless($vp, 404, 'VP tidak ditemukan.');
        $this->process($request, $vp, $request->action, (string) $request->note);
        return redirect()->route('approvals.index')->with('ok', $vp->reg_no.' telah diproses.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []); $ids = is_array($ids) ? $ids : [$ids];
        if (!$ids) return back()->withErrors(['ids' => 'Pilih minimal satu VP.']);
        $done = 0; $skipped = 0;
        foreach ($ids as $id) { try { $vp = DB::table('vp')->find((int) $id); if (!$vp) throw new \RuntimeException(); $this->process($request, $vp, 'setujui', 'Persetujuan terpilih'); $done++; } catch (\Throwable) { $skipped++; } }
        return back()->with('ok', $done.' VP disetujui'.($skipped ? '; '.$skipped.' dilewati karena status atau pemisahan tugas.' : '.'));
    }

    private function process(Request $request, object $vp, string $action, string $note): void
    {
        $user = $request->user(); $isChecker = $user->role === 'checker';
        if ($isChecker) { abort_unless($vp->status === 'DIAJUKAN', 409, 'VP tidak sedang menunggu pemeriksaan.'); abort_if($vp->maker_id === $user->id, 403, 'Anda tidak dapat memeriksa VP sendiri.'); }
        else { abort_unless($user->role === 'approver1' && $vp->status === 'DIPERIKSA', 409, 'VP tidak sedang menunggu persetujuan.'); abort_if($vp->maker_id === $user->id || $vp->checker_id === $user->id, 403, 'Anda tidak dapat menyetujui VP yang Anda buat atau periksa sendiri.'); }
        if (in_array($action, ['kembalikan', 'tolak'], true) && mb_strlen(trim($note)) < 5) abort(422, 'Catatan untuk Maker minimal 5 karakter.');
        DB::transaction(function () use ($request, $vp, $action, $note, $user, $isChecker) {
            $from = $vp->status;
            if ($action === 'setujui' && $isChecker) { DB::table('vp')->where('id', $vp->id)->update(['status' => 'DIPERIKSA', 'checker_id' => $user->id, 'checked_at' => now()]); $to = 'DIPERIKSA'; $history = 'Disetujui Checker'; $audit = 'VP_CHECKER_SETUJU'; }
            elseif ($action === 'setujui') { $lines = DB::table('vp_lines')->where('vp_id', $vp->id)->orderBy('id')->get(['name','bank','account_no','account_name','amount']); $hash = hash('sha256', json_encode(['vp' => $vp->reg_no, 'revision' => $vp->revision, 'total' => $vp->total, 'lines' => $lines], JSON_UNESCAPED_UNICODE)); DB::table('vp')->where('id', $vp->id)->update(['status' => 'DISETUJUI', 'approver1_id' => $user->id, 'approved_at' => now(), 'doc_hash' => $hash, 'verify_code' => bin2hex(random_bytes(8))]); $to = 'DISETUJUI'; $history = 'Disetujui Approver 1'; $audit = 'VP_APPROVER1_SETUJU'; }
            elseif ($action === 'tolak') { DB::table('vp')->where('id', $vp->id)->update(['status' => 'DITOLAK', 'return_note' => $note]); $to = 'DITOLAK'; $history = 'Ditolak Approver 1'; $audit = 'VP_APPROVER1_TOLAK'; }
            else { DB::table('vp')->where('id', $vp->id)->update(['status' => 'DIKEMBALIKAN', 'return_note' => $note]); $to = 'DIKEMBALIKAN'; $history = 'Dikembalikan ke Maker oleh '.($isChecker ? 'Checker' : 'Approver 1'); $audit = $isChecker ? 'VP_CHECKER_KEMBALI' : 'VP_APPROVER1_KEMBALI'; }
            DB::table('vp_history')->insert(['vp_id' => $vp->id, 'user_id' => $user->id, 'action' => $history, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'at' => now()]);
            DB::table('audit_log')->insert(['at' => now(), 'user_id' => $user->id, 'username' => $user->username, 'action' => $audit, 'entity' => 'vp', 'entity_id' => $vp->id, 'detail' => $vp->reg_no, 'ip' => $request->ip()]);
        });
    }
}
