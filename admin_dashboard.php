<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

// Get current admin type
$current_admin_type = $_SESSION['admin_type'] ?? 'other_admin';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle admin actions based on permissions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // Only super_admin and ict_admin can cancel bookings
    if ($action == 'cancel' && in_array($current_admin_type, ['super_admin', 'ict_admin'])) {
        $sql = "DELETE FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

// Handle user management based on permissions
if (isset($_GET['user_action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $user_action = $_GET['user_action'];
    
    // Permission checks
    $can_manage_users = in_array($current_admin_type, ['super_admin', 'ict_admin']);
    $can_delete_users = ($current_admin_type == 'super_admin');
    
    if ($user_action == 'delete' && $user_id != $_SESSION['user_id'] && $can_delete_users) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($user_action == 'toggle_admin' && $can_manage_users) {
        $sql = "UPDATE users SET role = IF(role='admin', 'user', 'admin') WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($user_action == 'toggle_active' && $can_manage_users) {
        $sql = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: admin_dashboard.php?tab=users");
    exit();
}

// Handle manual user creation with admin types
if (isset($_POST['create_user']) && in_array($current_admin_type, ['super_admin', 'ict_admin'])) {
    $username = trim($_POST['new_username']);
    $email = trim($_POST['new_email']);
    $password = $_POST['new_password'];
    $role = $_POST['new_role'];
    $admin_type = $_POST['new_admin_type'] ?? null;
    
    if (!empty($username) && !empty($email) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Set admin_type only for admin users
        if ($role == 'admin') {
            $sql = "INSERT INTO users (username, email, password, role, admin_type, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $username, $email, $hashed_password, $role, $admin_type, $_SESSION['user_id']);
        } else {
            $sql = "INSERT INTO users (username, email, password, role, created_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $_SESSION['user_id']);
        }
        
        $stmt->execute();
        $stmt->close();
        
        header("Location: admin_dashboard.php?tab=users");
        exit();
    }
}

// Handle room management
if (isset($_POST['create_room']) && in_array($current_admin_type, ['super_admin', 'ict_admin'])) {
    $name = trim($_POST['room_name']);
    $location = trim($_POST['room_location']);
    $floor = trim($_POST['room_floor']);
    $capacity = intval($_POST['room_capacity']);
    $equipment = trim($_POST['room_equipment']);
    $description = trim($_POST['room_description']);
    
    if (!empty($name) && !empty($location)) {
        $image_url = 'cdfpicture.jpg';
        
        $sql = "INSERT INTO rooms (name, location, floor, capacity, equipment, description, image_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssi", $name, $location, $floor, $capacity, $equipment, $description, $image_url, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        header("Location: admin_dashboard.php?tab=rooms");
        exit();
    }
}

// Handle meals & drinks management
if (isset($_POST['create_meal_drink']) && in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])) {
    $name = trim($_POST['item_name']);
    $category = $_POST['item_category'];
    $description = trim($_POST['item_description']);
    $available = isset($_POST['item_available']) ? 1 : 0;
    
    if (!empty($name) && !empty($category)) {
        $sql = "INSERT INTO meals_drinks (name, category, description, available, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $name, $category, $description, $available, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        header("Location: admin_dashboard.php?tab=meals");
        exit();
    }
}

// Handle meal/drink status toggle
if (isset($_GET['toggle_meal']) && isset($_GET['id']) && in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])) {
    $item_id = intval($_GET['id']);
    
    $sql = "UPDATE meals_drinks SET available = NOT available WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_dashboard.php?tab=meals");
    exit();
}

// Handle meal/drink deletion
if (isset($_GET['delete_meal']) && isset($_GET['id']) && in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])) {
    $item_id = intval($_GET['id']);
    
    $sql = "DELETE FROM meals_drinks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_dashboard.php?tab=meals");
    exit();
}

// Handle system actions (Super Admin only)
if (isset($_GET['system_action']) && $current_admin_type == 'super_admin') {
    $system_action = $_GET['system_action'];
    
    switch($system_action) {
        case 'clear_logs':
            $sql = "DELETE FROM system_logs";
            $conn->query($sql);
            $_SESSION['system_message'] = "System logs cleared successfully";
            break;
            
        case 'generate_report':
            $report_data = "NG-CDF Boardroom System Report\n";
            $report_data .= "Generated: " . date('Y-m-d H:i:s') . "\n";
            $report_data .= "================================\n\n";
            
            // System Information
            $report_data .= "SYSTEM INFORMATION:\n";
            $report_data .= "PHP Version: " . phpversion() . "\n";
            $report_data .= "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
            $report_data .= "Database: MySQL\n\n";
            
            // Statistics
            $stats_sql = "SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
                (SELECT COUNT(*) FROM bookings) as total_bookings,
                (SELECT COUNT(*) FROM rooms) as total_rooms,
                (SELECT COUNT(*) FROM meals_drinks) as total_items
                FROM DUAL";
            $stats_result = $conn->query($stats_sql);
            $stats = $stats_result->fetch_assoc();
            
            $report_data .= "STATISTICS:\n";
            $report_data .= "Total Users: " . ($stats['total_users'] ?? 0) . "\n";
            $report_data .= "Administrators: " . ($stats['admin_users'] ?? 0) . "\n";
            $report_data .= "Total Bookings: " . ($stats['total_bookings'] ?? 0) . "\n";
            $report_data .= "Total Rooms: " . ($stats['total_rooms'] ?? 0) . "\n";
            $report_data .= "Total Menu Items: " . ($stats['total_items'] ?? 0) . "\n";
            
            $report_file = 'system_report_' . date('Y-m-d') . '.txt';
            file_put_contents($report_file, $report_data);
            $_SESSION['system_message'] = "System report generated successfully. <a href='$report_file' class='underline'>Download Report</a>";
            break;
    }
    
    header("Location: admin_dashboard.php?tab=system");
    exit();
}

// Fetch data based on permissions
$bookings = [];
$users = [];
$rooms = [];
$meals_drinks = [];

// Always fetch bookings
$bookings_sql = "SELECT b.*, u.username, u.email, r.name as room_name, r.location 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN rooms r ON b.room_id = r.id 
                 ORDER BY b.created_at DESC";
$bookings_result = $conn->query($bookings_sql);
if ($bookings_result) {
    while($booking = $bookings_result->fetch_assoc()) {
        $bookings[] = $booking;
    }
}

// Fetch users (only for super_admin and ict_admin)
if (in_array($current_admin_type, ['super_admin', 'ict_admin'])) {
    $users_sql = "SELECT id, username, email, role, admin_type, is_active, created_at FROM users ORDER BY created_at DESC";
    $users_result = $conn->query($users_sql);
    if ($users_result) {
        while($user = $users_result->fetch_assoc()) {
            $users[] = $user;
        }
    }
}

// Fetch rooms (only for super_admin and ict_admin)
if (in_array($current_admin_type, ['super_admin', 'ict_admin'])) {
    $rooms_sql = "SELECT * FROM rooms ORDER BY name";
    $rooms_result = $conn->query($rooms_sql);
    if ($rooms_result) {
        while($room = $rooms_result->fetch_assoc()) {
            $rooms[] = $room;
        }
    }
}

// Fetch meals & drinks (for all admin types)
$meals_sql = "SELECT md.*, u.username as created_by_name 
              FROM meals_drinks md 
              JOIN users u ON md.created_by = u.id 
              ORDER BY md.category, md.name";
$meals_result = $conn->query($meals_sql);
if ($meals_result) {
    while($meal = $meals_result->fetch_assoc()) {
        $meals_drinks[] = $meal;
    }
}

// Fetch statistics
$stats = [];
$stats_sql = "SELECT 
    COUNT(*) as total_bookings,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
    (SELECT COUNT(*) FROM rooms) as total_rooms,
    (SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()) as today_bookings,
    (SELECT COUNT(*) FROM meals_drinks WHERE available = 1) as available_meals_drinks
    FROM bookings";
$stats_result = $conn->query($stats_sql);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
} else {
    // Default values if query fails
    $stats = [
        'total_bookings' => 0,
        'total_users' => 0,
        'admin_users' => 0,
        'total_rooms' => 0,
        'today_bookings' => 0,
        'available_meals_drinks' => 0
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NG-CDF Boardroom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- System Message Alert -->
    <?php if (isset($_SESSION['system_message'])): ?>
    <div class="fixed top-16 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg max-w-md">
        <div class="flex items-center justify-between">
            <div>
                <?= $_SESSION['system_message'] ?>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200 text-lg font-bold">×</button>
        </div>
    </div>
    <?php unset($_SESSION['system_message']); ?>
    <?php endif; ?>

    <!-- Custom Admin Navbar -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <img class="h-8 w-8 rounded" src="cdfpicture.jpg" alt="NG-CDF Logo">
                        <span class="ml-2 text-xl font-bold text-red-600 dark:text-red-400">
                            Admin Portal (<?= ucfirst(str_replace('_', ' ', $current_admin_type)) ?>)
                        </span>
                    </div>
                </div>
                
                <!-- Centered Navigation Menu -->
                <div class="flex items-center justify-center flex-1">
                    <div class="hidden sm:flex sm:items-center space-x-8">
                        <button onclick="showTab('dashboard')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-b-2 border-blue-600 text-blue-600 dark:text-blue-400">
                            <i data-feather="home" class="h-4 w-4 inline mr-2"></i>Dashboard
                        </button>
                        <button onclick="showTab('analytics')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="bar-chart-2" class="h-4 w-4 inline mr-2"></i>Analytics
                        </button>
                        <button onclick="showTab('bookings')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="calendar" class="h-4 w-4 inline mr-2"></i>Bookings
                        </button>
                        
                        <?php if (in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])): ?>
                        <button onclick="showTab('meals')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="coffee" class="h-4 w-4 inline mr-2"></i>Meals & Drinks
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
                        <button onclick="showTab('users')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="users" class="h-4 w-4 inline mr-2"></i>User Management
                        </button>
                        <button onclick="showTab('rooms')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="home" class="h-4 w-4 inline mr-2"></i>Room Management
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($current_admin_type == 'super_admin'): ?>
                        <button onclick="showTab('system')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button border-transparent">
                            <i data-feather="settings" class="h-4 w-4 inline mr-2"></i>System Tools
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right side items -->
                <div class="flex items-center space-x-4">
                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleDarkMode()" class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                        <i data-feather="moon" class="h-5 w-5"></i>
                    </button>
                    
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <i data-feather="log-out" class="w-4 h-4 inline mr-1"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="min-h-screen pt-16">
        <!-- Enhanced Statistics -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-feather="users" class="h-8 w-8 text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total_users'] ?? 0 ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $stats['admin_users'] ?? 0 ?> administrators</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-feather="calendar" class="h-8 w-8 text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Bookings</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total_bookings'] ?? 0 ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $stats['today_bookings'] ?? 0 ?> today</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-feather="home" class="h-8 w-8 text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Boardrooms</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total_rooms'] ?? 0 ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Active locations</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-feather="coffee" class="h-8 w-8 text-orange-600 dark:text-orange-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Items</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['available_meals_drinks'] ?? 0 ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Meals & Drinks</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Tab -->
            <div id="dashboard-content" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Quick Stats -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Overview</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Bookings</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 text-xs rounded-full"><?= $stats['total_bookings'] ?? 0 ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Today's Bookings</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 text-xs rounded-full"><?= $stats['today_bookings'] ?? 0 ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Active Users</span>
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 text-xs rounded-full"><?= $stats['total_users'] ?? 0 ?></span>
                            </div>
                            <?php if (in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Available Items</span>
                                <span class="px-2 py-1 bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 text-xs rounded-full"><?= $stats['available_meals_drinks'] ?? 0 ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Bookings</h3>
                        <div class="space-y-3">
                            <?php if (!empty($bookings)): ?>
                                <?php foreach (array_slice($bookings, 0, 5) as $booking): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($booking['event_name']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($booking['room_name']) ?> • <?= date('M j, g:i A', strtotime($booking['created_at'])) ?></p>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($booking['username']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No recent bookings</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics-content" class="tab-content hidden">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Analytics</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <h4 class="font-medium text-blue-900 dark:text-blue-100">Booking Trends</h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-2">Total: <?= $stats['total_bookings'] ?? 0 ?> bookings</p>
                            <p class="text-sm text-blue-700 dark:text-blue-300">Today: <?= $stats['today_bookings'] ?? 0 ?> bookings</p>
                        </div>
                        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <h4 class="font-medium text-green-900 dark:text-green-100">User Statistics</h4>
                            <p class="text-sm text-green-700 dark:text-green-300 mt-2">Total Users: <?= $stats['total_users'] ?? 0 ?></p>
                            <p class="text-sm text-green-700 dark:text-green-300">Admins: <?= $stats['admin_users'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Tab -->
            <div id="bookings-content" class="tab-content hidden">
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">All Bookings</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Manage all boardroom bookings</p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (!empty($bookings)): ?>
                                        <?php foreach ($bookings as $booking): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($booking['username']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($booking['email']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($booking['event_name']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($booking['attendees']) ?> attendees</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($booking['room_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= date('M j, Y', strtotime($booking['booking_date'])) ?><br>
                                                <span class="text-gray-500 dark:text-gray-400"><?= $booking['time_slot'] ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Confirmed
                                                </span>
                                            </td>
                                            <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="admin_dashboard.php?action=cancel&id=<?= $booking['id'] ?>" onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Cancel</a>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?= in_array($current_admin_type, ['super_admin', 'ict_admin']) ? '6' : '5' ?>" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No bookings found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Meals & Drinks Tab -->
            <?php if (in_array($current_admin_type, ['other_admin', 'super_admin', 'ict_admin'])): ?>
            <div id="meals-content" class="tab-content hidden">
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Meals & Drinks Management</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Manage available meals and drinks</p>
                        </div>
                        <button onclick="toggleMealForm()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i data-feather="plus" class="h-4 w-4 inline mr-1"></i>
                            Add Item
                        </button>
                    </div>

                    <!-- Add Item Form -->
                    <div id="mealForm" class="hidden px-4 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                                <input type="text" name="item_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                <select name="item_category" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    <option value="meal">Meal</option>
                                    <option value="drink">Drink</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea name="item_description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white"></textarea>
                            </div>
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="item_available" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Available</span>
                                </label>
                            </div>
                            <div class="md:col-span-2 flex space-x-3">
                                <button type="submit" name="create_meal_drink" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Create Item
                                </button>
                                <button type="button" onclick="toggleMealForm()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (!empty($meals_drinks)): ?>
                                        <?php foreach ($meals_drinks as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $item['category'] == 'meal' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' ?>">
                                                    <?= ucfirst($item['category']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($item['description']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $item['available'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                                    <?= $item['available'] ? 'Available' : 'Unavailable' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($item['created_by_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="admin_dashboard.php?toggle_meal=1&id=<?= $item['id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                    <?= $item['available'] ? 'Make Unavailable' : 'Make Available' ?>
                                                </a>
                                                <a href="admin_dashboard.php?delete_meal=1&id=<?= $item['id'] ?>" onclick="return confirm('Are you sure you want to delete this item?')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No items found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- User Management Tab (Super Admin & ICT Admin only) -->
            <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
            <div id="users-content" class="tab-content hidden">
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">User Management</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Manage system users and permissions</p>
                        </div>
                        <button onclick="toggleUserForm()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i data-feather="user-plus" class="h-4 w-4 inline mr-1"></i>
                            Add User
                        </button>
                    </div>

                    <!-- Add User Form -->
                    <div id="userForm" class="hidden px-4 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                                <input type="text" name="new_username" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                <input type="email" name="new_email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                                <input type="password" name="new_password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                                <select name="new_role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    <option value="user">User</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div id="adminTypeField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Type</label>
                                <select name="new_admin_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    <?php if ($current_admin_type == 'super_admin'): ?>
                                    <option value="super_admin">Super Admin</option>
                                    <?php endif; ?>
                                    <option value="ict_admin">ICT Admin</option>
                                    <option value="other_admin">Other Admin</option>
                                </select>
                            </div>
                            <div class="md:col-span-2 flex space-x-3">
                                <button type="submit" name="create_user" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Create User
                                </button>
                                <button type="button" onclick="toggleUserForm()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Admin Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Joined</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (!empty($users)): ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($user['username']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user['email']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $user['role'] == 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' ?>">
                                                    <?= ucfirst($user['role']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= $user['admin_type'] ? ucfirst(str_replace('_', ' ', $user['admin_type'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $user['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
                                                    <a href="admin_dashboard.php?user_action=toggle_admin&user_id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                        <?= $user['role'] == 'admin' ? 'Remove Admin' : 'Make Admin' ?>
                                                    </a>
                                                    <a href="admin_dashboard.php?user_action=toggle_active&user_id=<?= $user['id'] ?>" class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300 mr-3">
                                                        <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($current_admin_type == 'super_admin'): ?>
                                                    <a href="admin_dashboard.php?user_action=delete&user_id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        Delete
                                                    </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Room Management Tab (Super Admin & ICT Admin only) -->
            <?php if (in_array($current_admin_type, ['super_admin', 'ict_admin'])): ?>
            <div id="rooms-content" class="tab-content hidden">
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Room Management</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Manage boardrooms and facilities</p>
                        </div>
                        <button onclick="toggleRoomForm()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i data-feather="plus" class="h-4 w-4 inline mr-1"></i>
                            Add Room
                        </button>
                    </div>

                    <!-- Add Room Form -->
                    <div id="roomForm" class="hidden px-4 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Room Name</label>
                                <input type="text" name="room_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                                <input type="text" name="room_location" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Floor</label>
                                <input type="text" name="room_floor" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Capacity</label>
                                <input type="number" name="room_capacity" required min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Equipment</label>
                                <input type="text" name="room_equipment" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white" placeholder="Projector, Whiteboard, Video Conference...">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea name="room_description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white"></textarea>
                            </div>
                            <div class="md:col-span-2 flex space-x-3">
                                <button type="submit" name="create_room" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Create Room
                                </button>
                                <button type="button" onclick="toggleRoomForm()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Room Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Floor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Capacity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Equipment</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (!empty($rooms)): ?>
                                        <?php foreach ($rooms as $room): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($room['name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($room['location']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($room['floor']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                <?= $room['capacity'] ?> people
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($room['equipment']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Active
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No rooms found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- System Tools Tab (Super Admin only) -->
            <?php if ($current_admin_type == 'super_admin'): ?>
            <div id="system-content" class="tab-content hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Database Tools -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Database Management</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-blue-900 dark:text-blue-100">Database Backup</h4>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">Download complete database backup</p>
                                </div>
                                <a href="admin_dashboard.php?action=backup" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <i data-feather="download" class="h-4 w-4 inline mr-1"></i>
                                    Download
                                </a>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-green-900 dark:text-green-100">System Reports</h4>
                                    <p class="text-sm text-green-700 dark:text-green-300">Generate usage reports</p>
                                </div>
                                <a href="admin_dashboard.php?system_action=generate_report" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <i data-feather="file-text" class="h-4 w-4 inline mr-1"></i>
                                    Generate
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">PHP Version</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?= phpversion() ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Database</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">MySQL</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Users</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?= $stats['total_users'] ?? 0 ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Bookings</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white"><?= $stats['total_bookings'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 md:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <a href="admin_dashboard.php?system_action=clear_logs" onclick="return confirm('Are you sure you want to clear all system logs?')" class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors block">
                                <i data-feather="trash-2" class="h-6 w-6 text-red-600 dark:text-red-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-red-900 dark:text-red-100">Clear Logs</span>
                            </a>
                            <a href="admin_dashboard.php?system_action=generate_report" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors block">
                                <i data-feather="file-text" class="h-6 w-6 text-purple-600 dark:text-purple-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-purple-900 dark:text-purple-100">Generate Report</span>
                            </a>
                            <a href="admin_dashboard.php?tab=users" class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-center hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors block">
                                <i data-feather="users" class="h-6 w-6 text-indigo-600 dark:text-indigo-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-indigo-900 dark:text-indigo-100">User Management</span>
                            </a>
                            <a href="admin_dashboard.php?tab=rooms" class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors block">
                                <i data-feather="home" class="h-6 w-6 text-orange-600 dark:text-orange-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-orange-900 dark:text-orange-100">Room Management</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize icons
        feather.replace();
        
        // Tab management
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active styles from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-700', 'dark:text-gray-300');
            });
            
            // Show selected tab content
            const tabContent = document.getElementById(tabName + '-content');
            if (tabContent) {
                tabContent.classList.remove('hidden');
            }
            
            // Add active styles to selected tab button
            const activeButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            if (activeButton) {
                activeButton.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
                activeButton.classList.remove('border-transparent', 'text-gray-700', 'dark:text-gray-300');
            }
        }

        function toggleUserForm() {
            const form = document.getElementById('userForm');
            if (form) {
                form.classList.toggle('hidden');
            }
        }

        function toggleRoomForm() {
            const form = document.getElementById('roomForm');
            if (form) {
                form.classList.toggle('hidden');
            }
        }

        function toggleMealForm() {
            const form = document.getElementById('mealForm');
            if (form) {
                form.classList.toggle('hidden');
            }
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            feather.replace();
        }

        // Show/hide admin type field based on role selection
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.querySelector('select[name="new_role"]');
            const adminTypeField = document.getElementById('adminTypeField');
            
            if (roleSelect && adminTypeField) {
                roleSelect.addEventListener('change', function() {
                    if (this.value === 'admin') {
                        adminTypeField.classList.remove('hidden');
                    } else {
                        adminTypeField.classList.add('hidden');
                    }
                });
            }
            
            // Initialize dashboard with first tab
            showTab('dashboard');
        });
    </script>
</body>
</html>