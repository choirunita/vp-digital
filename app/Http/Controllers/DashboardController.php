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
        if ($user->role === 'maker') $query->where('maker_id', $user->id);
        $stats = (clone $query)->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total),0) as total')->groupBy('status')->get()->keyBy('status');
        return view('dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recent' => (clone $query)->orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
