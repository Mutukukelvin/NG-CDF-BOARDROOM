<?php
session_start();
include 'db_connect.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $login_type = $_POST['login_type']; // 'user' or 'admin'
        
        if (!empty($email) && !empty($password)) {
            $sql = "SELECT id, username, password, role, admin_type FROM users WHERE email = ? AND is_active = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check login type restrictions
                    if ($login_type == 'admin' && $user['role'] != 'admin') {
                        $error = "Access denied. Admin privileges required for admin login.";
                    } elseif ($login_type == 'user' && $user['role'] == 'admin') {
                        $error = "Administrators must use the admin login portal.";
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['admin_type'] = $user['admin_type'];
                        $_SESSION['email'] = $email;
                        
                        // Redirect based on role
                        if ($user['role'] == 'admin') {
                            header("Location: admin_dashboard.php");
                        } else {
                            header("Location: index.php");
                        }
                        exit();
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            $error = "Please fill in both fields.";
        }
    }
}

// Determine if this is an admin login page
$is_admin_login = basename($_SERVER['PHP_SELF']) == 'admin_login.php';
$page_title = $is_admin_login ? "Admin Login" : "User Login";
$login_type = $is_admin_login ? "admin" : "user";
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | NG-CDF Boardroom Booking</title>
    <link rel="icon" type="image/x-icon" href="/cdfpicture.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        .auth-bg {
            background: linear-gradient(135deg, 
                <?= $is_admin_login ? 'rgba(124, 58, 237, 0.9)' : 'rgba(30, 58, 138, 0.9)' ?> 0%, 
                <?= $is_admin_login ? 'rgba(139, 92, 246, 0.9)' : 'rgba(37, 99, 235, 0.9)' ?> 100%), 
                url('cdfpicture.jpg') no-repeat center center;
            background-size: cover;
        }
        .input-focus:focus {
            border-color: <?= $is_admin_login ? '#8b5cf6' : '#3b82f6' ?>;
            box-shadow: 0 0 0 3px <?= $is_admin_login ? 'rgba(139, 92, 246, 0.2)' : 'rgba(59, 130, 246, 0.2)' ?>;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Left Column (Form) -->
        <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24 z-10">
            <div class="mx-auto w-full max-w-md lg:w-96">
                <div class="text-center">
                    <div class="flex items-center justify-center">
                       <img class="h-12 w-12 rounded-lg" src="cdfpicture.jpg" alt="NG-CDF Logo">
                        <span class="ml-3 text-2xl font-bold <?= $is_admin_login ? 'text-purple-600' : 'text-red-600' ?>">
                            NG-CDF Boardrooms
                        </span>
                    </div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        <?= $is_admin_login ? 'Admin Portal Login' : 'Sign in to your account' ?>
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        <?php if ($is_admin_login): ?>
                            <span class="text-purple-600 font-medium">Administrator Access Only</span>
                        <?php else: ?>
                            Don't have an account? <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">Register here</a>
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!$is_admin_login): ?>
                    <div class="mt-4">
                        <a href="admin_login.php" class="text-sm text-purple-600 hover:text-purple-500 font-medium">
                            <i data-feather="shield" class="h-4 w-4 inline mr-1"></i>
                            Are you an administrator? Login here
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <a href="login.php" class="text-sm text-blue-600 hover:text-blue-500 font-medium">
                            <i data-feather="user" class="h-4 w-4 inline mr-1"></i>
                            Regular user login
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-8">
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <div class="flex items-center">
                                <i data-feather="alert-circle" class="h-4 w-4 mr-2"></i>
                                <span class="block sm:inline"><?= $error ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-6">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                            <?php if ($is_admin_login): ?>
                            <div class="bg-purple-100 border border-purple-400 text-purple-700 px-4 py-3 rounded">
                                <div class="flex items-center">
                                    <i data-feather="shield" class="h-4 w-4 mr-2"></i>
                                    <span class="text-sm">Administrator Access Portal</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email address
                                </label>
                                <div class="mt-1">
                                    <input id="email" name="email" type="email" autocomplete="email" required 
                                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-2 sm:text-sm input-focus"
                                           placeholder="Enter your email address"
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Password
                                </label>
                                <div class="mt-1">
                                    <input id="password" name="password" type="password" autocomplete="current-password" required 
                                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-2 sm:text-sm input-focus"
                                           placeholder="Enter your password">
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 <?= $is_admin_login ? 'text-purple-600 focus:ring-purple-500' : 'text-blue-600 focus:ring-blue-500' ?> border-gray-300 rounded">
                                    <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                        Remember me
                                    </label>
                                </div>

                                <div class="text-sm">
                                    <a href="#" class="font-medium <?= $is_admin_login ? 'text-purple-600 hover:text-purple-500' : 'text-blue-600 hover:text-blue-500' ?>">
                                        Forgot your password?
                                    </a>
                                </div>
                            </div>

                            <div>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="login_type" value="<?= $login_type ?>">
                                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white <?= $is_admin_login ? 'bg-purple-600 hover:bg-purple-700 focus:ring-purple-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500' ?> focus:outline-none focus:ring-2 focus:ring-offset-2 transition duration-300">
                                    <i data-feather="<?= $is_admin_login ? 'shield' : 'log-in' ?>" class="mr-2 h-4 w-4"></i>
                                    Sign in as <?= $is_admin_login ? 'Admin' : 'User' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Demo Access Section -->
                    <div class="mt-8">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-gray-50 text-gray-500">Demo Access</span>
                            </div>
                        </div>

                        <div class="mt-6 p-4 <?= $is_admin_login ? 'bg-purple-50 border-purple-200' : 'bg-blue-50 border-blue-200' ?> rounded-lg border">
                            <h4 class="text-sm font-medium <?= $is_admin_login ? 'text-purple-900' : 'text-blue-900' ?> mb-2">
                                <?= $is_admin_login ? 'Admin Demo Credentials' : 'Demo Credentials' ?>
                            </h4>
                            <p class="text-xs <?= $is_admin_login ? 'text-purple-700' : 'text-blue-700' ?> mb-3">
                                Use these credentials for testing:
                            </p>
                            <div class="text-xs <?= $is_admin_login ? 'text-purple-800' : 'text-blue-800' ?> space-y-1 mb-3">
                                <?php if ($is_admin_login): ?>
                                    <div class="grid grid-cols-1 gap-1">
                                        <div><strong>Super Admin:</strong> superadmin@ngcdf.go.ke</div>
                                        <div><strong>ICT Admin:</strong> ictadmin@ngcdf.go.ke</div>
                                        <div><strong>Other Admin:</strong> otheradmin@ngcdf.go.ke</div>
                                        <div><strong>Password:</strong> password123</div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 gap-1">
                                        <div><strong>Regular User:</strong> user@example.com</div>
                                        <div><strong>Password:</strong> password123</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button onclick="fillDemoCredentials()" class="w-full text-xs <?= $is_admin_login ? 'bg-purple-600 hover:bg-purple-700' : 'bg-blue-600 hover:bg-blue-700' ?> text-white py-2 px-3 rounded transition duration-300 flex items-center justify-center">
                                <i data-feather="user-check" class="h-3 w-3 mr-1"></i>
                                Fill Demo Credentials
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column (Welcome Section) -->
        <div class="hidden lg:block relative w-0 flex-1 auth-bg">
            <div class="absolute inset-0 flex items-center justify-end p-12"> 
                <div class="bg-white bg-opacity-95 p-10 rounded-lg max-w-md mr-12 shadow-xl">
                    <div class="flex items-center mb-6">
                        <img class="h-12 w-12 rounded-lg" src="cdfpicture.jpg" alt="NG-CDF Logo">
                        <h3 class="ml-3 text-3xl font-bold text-gray-800">
                            <?= $is_admin_login ? 'Admin Portal' : 'Welcome Back' ?>
                        </h3>
                    </div>
                    <p class="text-gray-600 mb-6 text-lg">
                        <?php if ($is_admin_login): ?>
                            Administrative dashboard for managing boardroom bookings, users, and system configuration.
                        <?php else: ?>
                            Efficient boardroom management for NG-CDF operations. Book meeting spaces with ease and confidence.
                        <?php endif; ?>
                    </p>
                    <div class="space-y-4 mb-8">
                        <?php if ($is_admin_login): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="settings" class="h-5 w-5 text-purple-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">System Management</p>
                                    <p class="text-xs text-gray-500">Manage users, rooms, and system settings</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="users" class="h-5 w-5 text-purple-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">User Administration</p>
                                    <p class="text-xs text-gray-500">Manage user accounts and permissions</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="bar-chart-2" class="h-5 w-5 text-purple-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">Analytics & Reports</p>
                                    <p class="text-xs text-gray-500">View booking statistics and generate reports</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="check-circle" class="h-5 w-5 text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">Real-time availability</p>
                                    <p class="text-xs text-gray-500">See available rooms instantly</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="check-circle" class="h-5 w-5 text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">Easy equipment booking</p>
                                    <p class="text-xs text-gray-500">Request projectors, video conferencing</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i data-feather="check-circle" class="h-5 w-5 text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700 font-medium">Multiple locations</p>
                                    <p class="text-xs text-gray-500">Access boardrooms across NG-CDF offices</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$is_admin_login): ?>
                    <!-- Register Link for User Login -->
                    <div class="pt-6 border-t border-gray-200">
                        <p class="text-sm text-gray-600 mb-4 text-center">Ready to get started?</p>
                        <a href="register.php" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                            <i data-feather="user-plus" class="mr-2 h-4 w-4"></i>
                            Create Your Account
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        AOS.init();
        feather.replace();

        function fillDemoCredentials() {
            <?php if ($is_admin_login): ?>
                document.getElementById('email').value = 'superadmin@ngcdf.go.ke';
            <?php else: ?>
                document.getElementById('email').value = 'user@example.com';
            <?php endif; ?>
            document.getElementById('password').value = 'password123';
        }

        // Add focus styles dynamically
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.classList.add('ring-2');
                });
                input.addEventListener('blur', function() {
                    this.classList.remove('ring-2');
                });
            });
        });
    </script>
</body>
</html>