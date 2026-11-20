<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $query = DB::table('vp');
        if ($user->role === 'maker') {
            $query->where('maker_id', $user->id);
        }
        $stats = (clone $query)->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total),0) as total')->groupBy('status')->get()->keyBy('status');

        $makerRows = DB::table('vp as v')
            ->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')
            ->select('v.*', 'p.bank as pcs_bank')
            ->selectSub(DB::table('vp_lines')->selectRaw('COUNT(*)')->whereColumn('vp_lines.vp_id', 'v.id'), 'n_lines')
            ->selectSub(DB::table('vp_lines')->select('name')->whereColumn('vp_lines.vp_id', 'v.id')->orderBy('id')->limit(1), 'first_name')
            ->where('v.maker_id', $user->id);

        return view('dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recent' => $user->role === 'maker'
                ? (clone $makerRows)->orderByDesc('v.id')->limit(8)->get()
                : (clone $query)->orderByDesc('id')->limit(8)->get(),
            'drafts' => $user->role === 'maker'
                ? (clone $makerRows)->whereIn('v.status', ['DRAFT', 'DIKEMBALIKAN'])->orderByDesc('v.id')->limit(5)->get()
                : collect(),
        ]);
    }
}
