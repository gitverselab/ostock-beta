<?php
// Ensure a session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the current user's role, default to 0 (guest) if not set
$user_role = $_SESSION['role_id'] ?? 0;
// Get the current page name to set the "active" class
$current_page = basename($_SERVER['PHP_SELF'], ".php");


// --- Central Navigation Configuration - Based on your structure with additions ---
// Roles: 1=Admin, 2=Employee, 3=OSP Admin
$nav_items = [
    ['title' => 'Dashboard', 'url' => 'dashboard', 'roles' => [1], 'icon' => 'fa-solid fa-tachometer-alt'],
    [
        'title' => 'Transaction',
        'roles' => [1, 2, 3],
        'icon' => 'fa-solid fa-arrows-spin',
        'children' => [
            ['title' => 'Inbound', 'url' => 'inbound', 'roles' => [1, 2, 3]],
            ['title' => 'Outbound', 'url' => 'outbound', 'roles' => [1, 2, 3]],
            ['title' => 'Transfer', 'url' => 'transfer', 'roles' => [1, 2, 3]],
            ['title' => 'Delivery', 'url' => 'delivery', 'roles' => [1, 2, 3]],
        ]
    ],
    [
        'title' => 'Production',
        'roles' => [1, 3],
        'icon' => 'fa-solid fa-industry',
        'children' => [
            ['title' => 'Bill of Materials', 'url' => 'manage_boms', 'roles' => [1, 3]],
            ['title' => 'Production Orders', 'url' => 'production_orders', 'roles' => [1, 3]],
            ['title' => 'Production Forecast', 'url' => 'forecasting', 'roles' => [1, 3]],
        ]
    ],
    [
        'title' => 'Transaction History',
        'roles' => [1, 2, 3],
        'icon' => 'fa-solid fa-exchange-alt',
        'children' => [
            ['title' => 'Inbound History', 'url' => 'inbound_history', 'roles' => [1, 2, 3]],
            ['title' => 'Outbound History', 'url' => 'outbound_history', 'roles' => [1, 2, 3]],
            ['title' => 'Transfer History', 'url' => 'transfer_history', 'roles' => [1, 2, 3]],
            ['title' => 'Delivery History', 'url' => 'delivery_history', 'roles' => [1, 2, 3]],
        ]
    ],
    [
        'title' => 'Item Convert',
        'roles' => [1, 2],
        'icon' => 'fa-solid fa-recycle',
        'children' => [
            ['title' => 'Frozen Conversion', 'url' => 'conversion_frozen', 'roles' => [1, 2]],
            ['title' => 'Chilled Conversion', 'url' => 'conversion_chilled', 'roles' => [1, 2]],
            ['title' => 'Box Conversion', 'url' => 'reboxing', 'roles' => [1, 2]],
        ]
    ],
    [
        'title' => 'Calendar',
        'roles' => [1, 2, 3],
        'icon' => 'fa-solid fa-calendar-alt',
        'children' => [
            ['title' => 'Calendar Schedule', 'url' => 'schedule_delivery', 'roles' => [1, 3]],
            ['title' => 'Calendar', 'url' => 'delivery_calendar', 'roles' => [1, 2, 3]],
        ]
    ],    
    [
        'title' => 'History',
        'roles' => [1],
        'icon' => 'fa-solid fa-history',
        'children' => [
            ['title' => 'Deleted History', 'url' => 'deleted_history', 'roles' => [1]],
            ['title' => 'Inventory History', 'url' => 'inventory_history', 'roles' => [1]],
        ]
    ],
    [
        'title' => 'Inventory',
        'roles' => [1, 2, 3],
        'icon' => 'fa-solid fa-boxes-storage',
        'children' => [
            ['title' => 'Items', 'url' => 'list_items', 'roles' => [1]],
            ['title' => 'Item Categories', 'url' => 'manage_categories', 'roles' => [1]],
            ['title' => 'Inventory Report', 'url' => 'inventory_report', 'roles' => [1, 2, 3]],
            ['title' => 'Valuation Report', 'url' => 'valuation_report', 'roles' => [1, 3]],
            ['title' => 'Warehouse', 'url' => 'manage_warehouses', 'roles' => [1]],
        ]
    ],
    [
        'title' => 'Settings',
        'roles' => [1],
        'icon' => 'fa-solid fa-cogs',
        'children' => [
            ['title' => 'Clients', 'url' => 'list_clients', 'roles' => [1]],
            ['title' => 'Roles', 'url' => 'roles', 'roles' => [1]],
            ['title' => 'Users', 'url' => 'users', 'roles' => [1]],
            ['title' => 'Permissions', 'url' => 'manage_permissions', 'roles' => [1]],
        ]
    ],
];
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3 fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard"><i class="fa-solid fa-boxes-stacked"></i> Inventory</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($nav_items as $item): ?>
                    <?php if (in_array($user_role, $item['roles'])): ?>
                        <?php if (isset($item['children'])): // Dropdown menu ?>
                            <?php
                                $is_dropdown_active = false;
                                foreach ($item['children'] as $child) {
                                    if ($current_page == $child['url']) {
                                        $is_dropdown_active = true;
                                        break;
                                    }
                                }
                            ?>
                            <li class="nav-item dropdown <?= $is_dropdown_active ? 'active' : '' ?>">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="<?= htmlspecialchars($item['icon'] ?? 'fa-solid fa-folder') ?>"></i> <?= htmlspecialchars($item['title']) ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <?php if (in_array($user_role, $child['roles'])): ?>
                                            <li><a class="dropdown-item <?= ($current_page == $child['url']) ? 'active' : '' ?>" href="<?= $child['url'] ?>"><?= htmlspecialchars($child['title']) ?></a></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: // Single link ?>
                            <li class="nav-item <?= ($current_page == $item['url']) ? 'active' : '' ?>">
                                <a class="nav-link" href="<?= $item['url'] ?>"><i class="<?= htmlspecialchars($item['icon'] ?? 'fa-solid fa-file-alt') ?>"></i> <?= htmlspecialchars($item['title']) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            
            <!-- User Menu on the right -->
            <?php if ($user_role > 0): ?>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fa-solid fa-user-edit"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>