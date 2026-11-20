<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrintController extends Controller
{
    public function index(): View
    {
        $rows = $this->rows();
        return view('print.index', ['ready' => $rows->where('status', 'DISETUJUI'), 'printed' => $rows->where('status', 'DICETAK')]);
    }

    public function show(int $id): View
    {
        $vp = DB::table('vp as v')->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')->leftJoin('users as maker', 'maker.id', '=', 'v.maker_id')->leftJoin('users as checker', 'checker.id', '=', 'v.checker_id')->leftJoin('users as approver', 'approver.id', '=', 'v.approver1_id')->select('v.*', 'p.bank as pcs_bank', 'p.account_no as pcs_account_no', 'p.account_name as pcs_account_name', 'maker.full_name as maker_name', 'checker.full_name as checker_name', 'approver.full_name as approver_name')->where('v.id', $id)->first();
        abort_unless($vp && in_array($vp->status, ['DISETUJUI', 'DICETAK', 'SELESAI'], true), 404);
        return view('print.vp', ['vp' => $vp, 'lines' => DB::table('vp_lines')->where('vp_id', $id)->orderBy('id')->get()]);
    }

    public function mark(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'sesdir', 403);
        $data = $request->validate(['action' => ['required', 'in:dicetak,selesai'], 'ids' => ['required','array']]);
        $count = 0;
        foreach ($data['ids'] as $id) {
            $vp = DB::table('vp')->find((int) $id); if (!$vp) continue;
            if ($data['action'] === 'dicetak' && $vp->status === 'DISETUJUI') {
                DB::table('vp')->where('id', $vp->id)->update(['status' => 'DICETAK', 'printed_by' => $request->user()->id, 'printed_at' => now()]);
                $this->history($request, $vp, 'DICETAK', 'Dicetak Sesdir dan diserahkan ke Approver 2 untuk tanda tangan basah'); $count++;
            }
            if ($data['action'] === 'selesai' && $vp->status === 'DICETAK') {
                DB::table('vp')->where('id', $vp->id)->update(['status' => 'SELESAI', 'completed_by' => $request->user()->id, 'completed_at' => now()]);
                $this->history($request, $vp, 'SELESAI', 'Dicatat sudah ditandatangani basah Approver 2'); $count++;
            }
        }
        return back()->with($count ? 'ok' : 'error', $count ? $count.' VP berhasil diperbarui.' : 'Tidak ada VP dengan status yang sesuai.');
    }

    private function rows()
    {
        return DB::table('vp as v')->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')->leftJoin('users as pr', 'pr.id', '=', 'v.printed_by')->select('v.*', 'p.bank as pcs_bank', 'pr.full_name as printed_name')->selectSub(DB::table('vp_lines')->selectRaw('COUNT(*)')->whereColumn('vp_lines.vp_id', 'v.id'), 'n_lines')->selectSub(DB::table('vp_lines')->select('name')->whereColumn('vp_lines.vp_id', 'v.id')->orderBy('id')->limit(1), 'first_name')->whereIn('v.status', ['DISETUJUI','DICETAK'])->orderBy('v.status')->orderByDesc('v.approved_at')->get();
    }

    private function history(Request $request, object $vp, string $to, string $action): void
    {
        DB::table('vp_history')->insert(['vp_id' => $vp->id, 'user_id' => $request->user()->id, 'action' => $action, 'from_status' => $vp->status, 'to_status' => $to, 'note' => '', 'at' => now()]);
        DB::table('audit_log')->insert(['at' => now(), 'user_id' => $request->user()->id, 'username' => $request->user()->username, 'action' => 'VP_'.$to, 'entity' => 'vp', 'entity_id' => $vp->id, 'detail' => $vp->reg_no, 'ip' => $request->ip()]);
    }
}
