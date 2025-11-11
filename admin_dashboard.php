<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

// Handle admin actions - Simplified for auto-booking system
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // Only keep cancel functionality for auto-booking system
    if ($action == 'cancel') {
        $sql = "DELETE FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

// Handle user management
if (isset($_GET['user_action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $user_action = $_GET['user_action'];
    
    if ($user_action == 'delete' && $user_id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($user_action == 'toggle_admin') {
        $sql = "UPDATE users SET role = IF(role='admin', 'user', 'admin') WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

// Handle manual user creation
if (isset($_POST['create_user'])) {
    $username = trim($_POST['new_username']);
    $email = trim($_POST['new_email']);
    $password = $_POST['new_password'];
    $role = $_POST['new_role'];
    
    if (!empty($username) && !empty($email) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
        $stmt->execute();
        $stmt->close();
        
        header("Location: admin_dashboard.php?tab=users");
        exit();
    }
}

// Handle system actions
if (isset($_GET['system_action'])) {
    $system_action = $_GET['system_action'];
    
    switch($system_action) {
        case 'clear_logs':
            // Clear system logs - Only file-based logs since we don't have system_logs table
            $log_files = [
                'system.log',
                'error.log', 
                'access.log',
                'debug.log'
            ];
            
            foreach($log_files as $log_file) {
                if(file_exists($log_file)) {
                    file_put_contents($log_file, "Log cleared on: " . date('Y-m-d H:i:s') . "\n");
                }
            }
            
            $_SESSION['system_message'] = "System logs cleared successfully";
            break;
            
        case 'reset_cache':
            // Clear file-based cache only (no database cache table)
            $cache_dirs = ['cache/', 'tmp/', 'sessions/'];
            
            foreach($cache_dirs as $dir) {
                if(is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach($files as $file) {
                        if(is_file($file)) {
                            unlink($file);
                        }
                    }
                }
            }
            
            // Clear opcache if enabled
            if(function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            $_SESSION['system_message'] = "Cache reset successfully";
            break;
            
        case 'security_scan':
            $security_report = [];
            
            // 1. Check file permissions
            $sensitive_files = [
                'config.php' => '0644',
                '.env' => '0600',
                'db_connect.php' => '0644'
            ];
            
            foreach($sensitive_files as $file => $expected_perms) {
                if(file_exists($file)) {
                    $actual_perms = substr(sprintf('%o', fileperms($file)), -4);
                    if($actual_perms != $expected_perms) {
                        $security_report[] = "⚠️ File permission issue: $file (Current: $actual_perms, Expected: $expected_perms)";
                    }
                }
            }
            
            // 2. Check for common vulnerabilities
            if(ini_get('display_errors') == 1) {
                $security_report[] = "⚠️ Display errors is enabled - should be disabled in production";
            }
            
            // 3. Check for exposed directories
            $exposed_dirs = ['admin/', 'includes/', 'config/'];
            foreach($exposed_dirs as $dir) {
                if(is_dir($dir) && !file_exists($dir . 'index.php')) {
                    $security_report[] = "⚠️ Directory listing possible: $dir - add index.php file";
                }
            }
            
            // 4. Check PHP version
            if(version_compare(PHP_VERSION, '8.0.0', '<')) {
                $security_report[] = "⚠️ Using older PHP version: " . PHP_VERSION . " - consider upgrading";
            }
            
            // 5. Check database connection security
            if(strpos($conn->host_info, 'localhost') === false && strpos($conn->host_info, '127.0.0.1') === false) {
                $security_report[] = "⚠️ Database connection might be remote - ensure SSL is enabled";
            }
            
            if(empty($security_report)) {
                $_SESSION['system_message'] = "✅ Security scan completed - No major issues found";
            } else {
                $report_file = 'security_scan_report_' . date('Y-m-d_H-i-s') . '.txt';
                file_put_contents($report_file, "Security Scan Report - " . date('Y-m-d H:i:s') . "\n");
                file_put_contents($report_file, "==========================================\n", FILE_APPEND);
                file_put_contents($report_file, implode("\n", $security_report), FILE_APPEND);
                $_SESSION['system_message'] = "Security scan completed - " . count($security_report) . " issues found. <a href='$report_file' class='underline'>Download Report</a>";
            }
            break;
            
        case 'generate_report':
            // Generate system report
            $report_data = "NG-CDF Boardroom System Report\n";
            $report_data .= "Generated: " . date('Y-m-d H:i:s') . "\n";
            $report_data .= "================================\n\n";
            
            // System Information
            $report_data .= "SYSTEM INFORMATION:\n";
            $report_data .= "PHP Version: " . phpversion() . "\n";
            $report_data .= "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
            $report_data .= "Database: MySQL\n\n";
            
            // Statistics
            $report_data .= "STATISTICS:\n";
            $report_data .= "Total Users: " . ($stats['total_users'] ?? 0) . "\n";
            $report_data .= "Administrators: " . ($stats['admin_users'] ?? 0) . "\n";
            $report_data .= "Total Bookings: " . ($stats['total_bookings'] ?? 0) . "\n";
            $report_data .= "Active Rooms: " . ($stats['total_rooms'] ?? 0) . "\n";
            $report_data .= "Today's Bookings: " . ($stats['today_bookings'] ?? 0) . "\n";
            
            $report_file = 'system_report_' . date('Y-m-d') . '.txt';
            file_put_contents($report_file, $report_data);
            $_SESSION['system_message'] = "System report generated successfully. <a href='$report_file' class='underline'>Download Report</a>";
            break;
    }
    
    header("Location: admin_dashboard.php?tab=system");
    exit();
}

// Handle database backup
if (isset($_GET['action']) && $_GET['action'] == 'backup') {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="ngcdf_boardroom_backup_' . date('Y-m-d') . '.sql"');
    
    // Get database structure and data
    $tables = ['users', 'rooms', 'bookings'];
    $output = "-- NG-CDF Boardroom Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        $output .= "-- Table: $table\n";
        $result = $conn->query("SHOW CREATE TABLE $table");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $output .= $row['Create Table'] . ";\n\n";
            
            $result_data = $conn->query("SELECT * FROM $table");
            if ($result_data && $result_data->num_rows > 0) {
                while ($row = $result_data->fetch_assoc()) {
                    $output .= "INSERT INTO $table VALUES(";
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                    $output .= implode(', ', $values) . ");\n";
                }
            }
            $output .= "\n";
        }
    }
    
    echo $output;
    exit();
}

// Check system status
$system_status = 'Online';
$system_message = 'All systems operational';

// Check database connection
if (!$conn || $conn->connect_error) {
    $system_status = 'Offline';
    $system_message = 'Database connection failed';
}

// Check if essential tables exist
$required_tables = ['users', 'rooms', 'bookings'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $system_status = 'Degraded';
        $system_message = "Missing table: $table";
        break;
    }
}

// Fetch all bookings with explicit table aliases
$bookings = [];
$sql = "SELECT b.*, u.username, u.email, r.name as room_name, r.location, 
               b.created_at as booking_created, u.created_at as user_created
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN rooms r ON b.room_id = r.id 
        ORDER BY b.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($booking = $result->fetch_assoc()) {
        $bookings[] = $booking;
    }
}

// Fetch all users
$users = [];
$users_sql = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);
if ($users_result && $users_result->num_rows > 0) {
    while($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
}

// Fetch advanced statistics - UPDATED QUERIES (removed status references)
$stats = [];
$stats_sql = "SELECT 
    COUNT(*) as total_bookings,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
    (SELECT COUNT(*) FROM rooms) as total_rooms,
    (SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()) as today_bookings
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
        'today_bookings' => 0
    ];
}

// Fetch room usage statistics - UPDATED QUERY (removed status references)
$room_stats = [];
$room_stats_sql = "SELECT r.id, r.name, 
                   COUNT(b.id) as booking_count
                   FROM rooms r 
                   LEFT JOIN bookings b ON r.id = b.room_id
                   GROUP BY r.id, r.name 
                   ORDER BY booking_count DESC";
$room_stats_result = $conn->query($room_stats_sql);
if ($room_stats_result) {
    while($room_stat = $room_stats_result->fetch_assoc()) {
        $room_stats[] = $room_stat;
    }
}

// Fetch recent system activity with explicit table aliases
$recent_activity = [];
$activity_sql = "SELECT 'booking' as type, b.event_name as description, b.created_at, u.username 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 UNION ALL 
                 SELECT 'user' as type, CONCAT('New user: ', username) as description, created_at, username 
                 FROM users 
                 ORDER BY created_at DESC 
                 LIMIT 10";
$activity_result = $conn->query($activity_sql);
if ($activity_result) {
    while($activity = $activity_result->fetch_assoc()) {
        $recent_activity[] = $activity;
    }
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
                        <span class="ml-2 text-xl font-bold text-red-600 dark:text-red-400">Admin Portal</span>
                    </div>
                </div>
                
                <!-- Centered Navigation Menu -->
                <div class="flex items-center justify-center flex-1">
                    <div class="hidden sm:flex sm:items-center space-x-8">
                        <button onclick="showTab('dashboard')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button <?= (isset($_GET['tab']) && $_GET['tab'] == 'dashboard') || !isset($_GET['tab']) ? 'border-b-2 border-blue-600' : '' ?>">
                            <i data-feather="home" class="h-4 w-4 inline mr-2"></i>Dashboard
                        </button>
                        <button onclick="showTab('analytics')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button <?= isset($_GET['tab']) && $_GET['tab'] == 'analytics' ? 'border-b-2 border-blue-600' : '' ?>">
                            <i data-feather="bar-chart-2" class="h-4 w-4 inline mr-2"></i>Analytics
                        </button>
                        <button onclick="showTab('bookings')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button <?= isset($_GET['tab']) && $_GET['tab'] == 'bookings' ? 'border-b-2 border-blue-600' : '' ?>">
                            <i data-feather="calendar" class="h-4 w-4 inline mr-2"></i>Bookings
                        </button>
                        <button onclick="showTab('users')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button <?= isset($_GET['tab']) && $_GET['tab'] == 'users' ? 'border-b-2 border-blue-600' : '' ?>">
                            <i data-feather="users" class="h-4 w-4 inline mr-2"></i>User Management
                        </button>
                        <button onclick="showTab('system')" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 text-sm font-medium tab-button <?= isset($_GET['tab']) && $_GET['tab'] == 'system' ? 'border-b-2 border-blue-600' : '' ?>">
                            <i data-feather="settings" class="h-4 w-4 inline mr-2"></i>System Tools
                        </button>
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
                
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 <?= $system_status == 'Online' ? 'border-green-500' : ($system_status == 'Degraded' ? 'border-yellow-500' : 'border-red-500') ?>">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-feather="activity" class="h-8 w-8 <?= $system_status == 'Online' ? 'text-green-600 dark:text-green-400' : ($system_status == 'Degraded' ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">System Status</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $system_status ?></p>
                            <p class="text-xs <?= $system_status == 'Online' ? 'text-green-600 dark:text-green-400' : ($system_status == 'Degraded' ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>"><?= $system_message ?></p>
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
                        </div>
                    </div>

                    <!-- Room Usage -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Room Usage Ranking</h3>
                        <div class="space-y-3">
                            <?php if (!empty($room_stats)): ?>
                                <?php foreach ($room_stats as $index => $room): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400"><?= $index + 1 ?>. <?= htmlspecialchars($room['name']) ?></span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 text-xs rounded-full"><?= $room['booking_count'] ?> bookings</span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No room usage data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent System Activity</h3>
                        <div class="space-y-3">
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex items-center">
                                        <i data-feather="<?= $activity['type'] == 'booking' ? 'calendar' : 'user' ?>" class="h-4 w-4 text-gray-400 mr-3"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($activity['description']) ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">By <?= htmlspecialchars($activity['username']) ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics-content" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Booking Statistics Chart -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Overview</h3>
                        <div class="h-64">
                            <canvas id="bookingChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- User Distribution -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Distribution</h3>
                        <div class="h-64">
                            <canvas id="userChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Room Performance -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Room Performance</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Utilization Rate</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (!empty($room_stats)): ?>
                                        <?php foreach ($room_stats as $room): ?>
                                        <tr>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($room['name']) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?= $room['booking_count'] ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                                <?php 
                                                $utilization_rate = ($stats['total_bookings'] > 0) ? round(($room['booking_count'] / $stats['total_bookings']) * 100) : 0;
                                                echo $utilization_rate . '%';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 text-xs rounded-full">Active</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No room data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                        <a href="admin_dashboard.php?action=backup" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i data-feather="download" class="h-4 w-4 inline mr-1"></i>
                            Export Report
                        </a>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
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
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="admin_dashboard.php?action=cancel&id=<?= $booking['id'] ?>" onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Cancel</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No bookings found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
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
                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="admin_dashboard.php?user_action=toggle_admin&user_id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                                        <?= $user['role'] == 'admin' ? 'Remove Admin' : 'Make Admin' ?>
                                                    </a>
                                                    <a href="admin_dashboard.php?user_action=delete&user_id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        Delete
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400 dark:text-gray-500">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Tab -->
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
                            <a href="admin_dashboard.php?system_action=reset_cache" onclick="return confirm('Are you sure you want to reset all cache?')" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors block">
                                <i data-feather="refresh-cw" class="h-6 w-6 text-purple-600 dark:text-purple-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-purple-900 dark:text-purple-100">Reset Cache</span>
                            </a>
                            <a href="admin_dashboard.php?system_action=security_scan" class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors block">
                                <i data-feather="shield" class="h-6 w-6 text-orange-600 dark:text-orange-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-orange-900 dark:text-orange-100">Security Scan</span>
                            </a>
                            <a href="admin_dashboard.php?tab=analytics" class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-center hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors block">
                                <i data-feather="bar-chart" class="h-6 w-6 text-indigo-600 dark:text-indigo-400 mx-auto mb-2"></i>
                                <span class="text-sm font-medium text-indigo-900 dark:text-indigo-100">Analytics</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize icons
        feather.replace();
        
        // Tab management
        function showTab(tabName) {
            console.log('Showing tab:', tabName);
            
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
                console.log('Tab content shown');
            } else {
                console.error('Tab content not found:', tabName + '-content');
            }
            
            // Add active styles to selected tab button
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                if (button.textContent.includes(tabName.charAt(0).toUpperCase() + tabName.slice(1)) || 
                    button.textContent.includes('Dashboard') && tabName === 'dashboard') {
                    button.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
                    button.classList.remove('border-transparent', 'text-gray-700', 'dark:text-gray-300');
                }
            });
        }

        function toggleUserForm() {
            const form = document.getElementById('userForm');
            if (form) {
                form.classList.toggle('hidden');
            }
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            feather.replace();
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, initializing tabs...');
            
            // Add click events to all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', function() {
                    const tabText = this.textContent.trim();
                    let tabName = '';
                    
                    if (tabText.includes('Dashboard')) tabName = 'dashboard';
                    else if (tabText.includes('Analytics')) tabName = 'analytics';
                    else if (tabText.includes('Bookings')) tabName = 'bookings';
                    else if (tabText.includes('User Management')) tabName = 'users';
                    else if (tabText.includes('System Tools')) tabName = 'system';
                    
                    console.log('Tab button clicked:', tabName);
                    showTab(tabName);
                });
            });
            
            // Check URL for tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam && document.getElementById(tabParam + '-content')) {
                console.log('Showing tab from URL:', tabParam);
                showTab(tabParam);
            } else {
                console.log('Showing default tab: dashboard');
                showTab('dashboard');
            }
            
            // Initialize charts if they exist
            initializeCharts();
        });

        function initializeCharts() {
            // System Overview Chart - Updated for final bookings
            const bookingCtx = document.getElementById('bookingChart');
            if (bookingCtx) {
                console.log('Initializing booking chart');
                new Chart(bookingCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Total Bookings', 'Today\'s Bookings', 'Active Users', 'Boardrooms'],
                        datasets: [{
                            label: 'System Overview',
                            data: [
                                <?= $stats['total_bookings'] ?? 0 ?>,
                                <?= $stats['today_bookings'] ?? 0 ?>,
                                <?= $stats['total_users'] ?? 0 ?>,
                                <?= $stats['total_rooms'] ?? 0 ?>
                            ],
                            backgroundColor: [
                                '#3b82f6',
                                '#10b981', 
                                '#8b5cf6',
                                '#f59e0b'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // User Distribution Chart
            const userCtx = document.getElementById('userChart');
            if (userCtx) {
                console.log('Initializing user chart');
                new Chart(userCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Administrators', 'Regular Users'],
                        datasets: [{
                            data: [
                                <?= $stats['admin_users'] ?? 0 ?>,
                                <?= ($stats['total_users'] ?? 0) - ($stats['admin_users'] ?? 0) ?>
                            ],
                            backgroundColor: [
                                '#8b5cf6',
                                '#3b82f6'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }
    </script>
</body>
</html>