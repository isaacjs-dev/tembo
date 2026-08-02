<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $periodDays = $request->get('period', 30);
        $since = now()->subDays($periodDays);

        // KPIs de Usuários
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $inactiveUsers = User::where('status', 'inactive')->count();
        $pendingUsers = User::where('status', 'pending')->count();
        $blockedUsers = User::where('status', 'blocked')->count();

        // KPIs por perfil
        $totalTeachers = User::where('type', 'teacher')->count();
        $totalStudents = User::where('type', 'student')->count();
        $totalInstitutionAdmins = User::where('type', 'institution_admin')->count();

        // KPIs de recursos
        $totalOrgs = Organization::count();
        $totalClasses = SchoolClass::count();
        $totalPlans = Plan::count();
        $activePlans = Plan::where('status', 'active')->count();

        // KPIs de convites
        $pendingInvites = Invite::where('status', 'pending')->count();
        $acceptedInvites = Invite::where('status', 'accepted')->count();
        $declinedInvites = Invite::where('status', 'declined')->count();

        // Assinaturas
        $activeSubscriptions = Subscription::where('status', 'active')->count();

        // Distribuição por plano
        $planDistribution = Plan::withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('subscriptions_count')
            ->get(['id', 'name', 'tier_level']);

        // Atividade recente (últimos N dias)
        $recentLogs = AuditLog::where('created_at', '>=', $since)->count();
        $recentUsers = User::where('created_at', '>=', $since)->count();

        // Novos registros no período (para variação %)
        $previousSince = now()->subDays($periodDays * 2);
        $previousUsers = User::whereBetween('created_at', [$previousSince, $since])->count();
        $usersGrowth = $previousUsers > 0 ? round(($recentUsers - $previousUsers) / $previousUsers * 100) : 0;

        return view('admin.dashboard', compact(
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'pendingUsers',
            'blockedUsers',
            'totalTeachers',
            'totalStudents',
            'totalInstitutionAdmins',
            'totalOrgs',
            'totalClasses',
            'totalPlans',
            'activePlans',
            'pendingInvites',
            'acceptedInvites',
            'declinedInvites',
            'activeSubscriptions',
            'planDistribution',
            'recentLogs',
            'recentUsers',
            'usersGrowth',
            'periodDays'
        ));
    }
}
