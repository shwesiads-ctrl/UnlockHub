<?php

namespace App\Support;

use App\Models\User;

/**
 * Staff roles and permissions.
 *
 * Every admin route is mapped to a permission (ROUTES). The CheckStaffPermission middleware
 * blocks the URL itself, not just the menu. A route missing from the map is Owner-only,
 * so new pages are safe by default.
 */
class Permissions
{
    public const ROLES = [
        'owner' => 'Owner',
        'finance' => 'Finance',
        'operator' => 'Order Operator',
        'support' => 'Support',
        'editor' => 'Content Editor',
    ];

    public const ROLE_HINTS = [
        'owner' => 'Everything, including staff, settings and supplier API keys.',
        'finance' => 'Payments, wallets, reconciliation, reports.',
        'operator' => 'Process orders and enter results.',
        'support' => 'Answer tickets, look up customers and orders.',
        'editor' => 'Blog, knowledge base, announcements, popups.',
    ];

    /** permission => [group, label] */
    public const ALL = [
        'dashboard.view' => ['General', 'See the dashboard'],
        'orders.view' => ['Orders', 'View orders'],
        'orders.process' => ['Orders', 'Process orders / enter results'],
        'payments.view' => ['Payments', 'View top-ups and receipts'],
        'payments.approve' => ['Payments', 'Approve / reject top-ups'],
        'payments.methods' => ['Payments', 'Edit payment methods'],
        'users.view' => ['Customers', 'View customers'],
        'users.update' => ['Customers', 'Edit customers, reset 2FA / password'],
        'wallet.view' => ['Wallet', 'View statements'],
        'wallet.adjust' => ['Wallet', 'Add / remove wallet balance'],
        'wallet.approve' => ['Wallet', 'Approve large wallet adjustments (second person)'],
        'reconcile.view' => ['Wallet', 'View wallet reconciliation'],
        'reconcile.approve' => ['Wallet', 'Approve reconciliation corrections'],
        'tickets.view' => ['Support', 'View tickets'],
        'tickets.reply' => ['Support', 'Reply, change status, internal notes'],
        'tickets.assign' => ['Support', 'Assign tickets, manage saved replies'],
        'services.manage' => ['Catalog', 'Services, groups, popups'],
        'pricing.manage' => ['Catalog', 'Customer groups & pricing'],
        'points.manage' => ['Catalog', 'Points rules and adjustments'],
        'suppliers.manage' => ['Protected', 'Suppliers & API keys'],
        'settings.manage' => ['Protected', 'Site settings, social login & Telegram secrets'],
        'staff.manage' => ['Protected', 'Staff and permissions'],
        'content.manage' => ['Content', 'Blog, testimonials, downloads, announcements'],
        'reports.view' => ['Reports', 'View reports'],
        'reports.export' => ['Reports', 'Export reports (CSV)'],
        'audit.view' => ['Reports', 'View activity / audit log'],
    ];

    /** Never given to anyone but Owner, whatever is ticked. */
    public const OWNER_ONLY = ['staff.manage'];

    public const DEFAULTS = [
        'finance' => ['dashboard.view', 'orders.view', 'payments.view', 'payments.approve', 'users.view', 'wallet.view', 'wallet.adjust', 'wallet.approve', 'reconcile.view', 'reconcile.approve', 'reports.view', 'reports.export'],
        'operator' => ['dashboard.view', 'orders.view', 'orders.process', 'users.view', 'tickets.view'],
        'support' => ['dashboard.view', 'orders.view', 'users.view', 'tickets.view', 'tickets.reply'],
        'editor' => ['dashboard.view', 'content.manage', 'services.manage'],
    ];

    /** Route name (admin.*) pattern => permission. First match wins. */
    public const ROUTES = [
        'admin.dashboard' => 'dashboard.view',
        'admin.orders.update' => 'orders.process',
        'admin.orders.bulk-in-process' => 'orders.process',
        'admin.orders.retry' => 'orders.process',
        'admin.orders.*' => 'orders.view',
        'admin.payments.approve' => 'payments.approve',
        'admin.payments.online.sync' => 'payments.approve',
        'admin.payments.reject' => 'payments.approve',
        'admin.payments.*' => 'payments.view',
        'admin.payment-methods.*' => 'payments.methods',
        'admin.users.wallet' => 'wallet.adjust',
        'admin.users.statement*' => 'wallet.view',
        'admin.users.index' => 'users.view',
        'admin.users.show' => 'users.view',
        'admin.users.*' => 'users.update',
        'admin.wallet-approvals.index' => 'wallet.view',
        'admin.wallet-approvals.*' => 'wallet.approve',
        'admin.reconcile.index' => 'reconcile.view',
        'admin.reconcile.show' => 'reconcile.view',
        'admin.reconcile.export' => 'reconcile.view',
        'admin.reconcile.*' => 'reconcile.approve',
        'admin.tickets.assign' => 'tickets.assign',
        'admin.tickets.bulk' => 'tickets.assign',
        'admin.canned.*' => 'tickets.assign',
        'admin.tickets.index' => 'tickets.view',
        'admin.tickets.show' => 'tickets.view',
        'admin.tickets.attachment' => 'tickets.view',
        'admin.tickets.*' => 'tickets.reply',
        'admin.services.*' => 'services.manage',
        'admin.categories.*' => 'services.manage',
        'admin.popups.*' => 'services.manage',
        'admin.groups.*' => 'pricing.manage',
        'admin.pricing.*' => 'pricing.manage',
        'admin.points.*' => 'points.manage',
        'admin.suppliers.*' => 'suppliers.manage',
        'admin.settings.*' => 'settings.manage',
        'admin.staff.*' => 'staff.manage',
        'admin.posts.*' => 'content.manage',
        'admin.testimonials.*' => 'content.manage',
        'admin.downloads.*' => 'content.manage',
        'admin.announcements.*' => 'content.manage',
        'admin.reports.export' => 'reports.export',
        'admin.reports.delivery.refresh' => 'reports.view',
        'admin.reports.*' => 'reports.view',
        'admin.activity.*' => 'audit.view',
    ];

    public static function forRoute(?string $name): ?string
    {
        foreach (self::ROUTES as $pattern => $perm) {
            if ($name !== null && \Illuminate\Support\Str::is($pattern, $name)) {
                return $perm;
            }
        }

        return null; // unmapped → Owner only
    }

    /** Permissions per role (saved by Owner, falling back to DEFAULTS). */
    public static function matrix(): array
    {
        $saved = json_decode((string) setting('staff.permissions', ''), true);
        $out = [];
        foreach (array_keys(self::ROLES) as $role) {
            if ($role === 'owner') {
                $out[$role] = array_keys(self::ALL);

                continue;
            }
            $list = is_array($saved[$role] ?? null) ? $saved[$role] : (self::DEFAULTS[$role] ?? []);
            $out[$role] = array_values(array_diff(array_intersect($list, array_keys(self::ALL)), self::OWNER_ONLY));
        }

        return $out;
    }

    public static function allows(?User $user, string $permission): bool
    {
        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            return false;
        }

        $role = $user->staffRole();

        return $role === 'owner' || in_array($permission, self::matrix()[$role] ?? [], true);
    }

    /** Route name → may this user open it? */
    public static function allowsRoute(?User $user, ?string $routeName): bool
    {
        $perm = self::forRoute($routeName);

        return $perm ? self::allows($user, $perm) : ($user?->isAdmin() && $user->staffRole() === 'owner');
    }
}
