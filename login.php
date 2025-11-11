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
        $is_admin_login = isset($_POST['admin_login']) && $_POST['admin_login'] == 'true';
        
        if (!empty($email) && !empty($password)) {
            $sql = "SELECT id, username, password, role FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check if admin login is required and user is admin
                    if ($is_admin_login && $user['role'] != 'admin') {
                        $error = "Access denied. Admin privileges required.";
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NG-CDF Boardroom Booking</title>
    <link rel="icon" type="image/x-icon" href="/cdfpicture.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .auth-bg {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.9) 0%, rgba(37, 99, 235, 0.9) 100%), url('cdfpicture.jpg') no-repeat center center;
            background-size: cover;
        }
        .input-focus:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .admin-login-active {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.9) 0%, rgba(139, 92, 246, 0.9) 100%);
        }
        .user-login-active {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.9) 0%, rgba(37, 99, 235, 0.9) 100%);
        }
        .login-type-btn {
            transition: all 0.3s ease;
        }
        .login-type-btn.active {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
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
                        <span class="ml-3 text-2xl font-bold text-red-600">NG-CDF Boardrooms</span>
                    </div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        Sign in to your account
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Don't have an account? <a href="register.php" class="font-medium text-blue-600 hover:text-red-500">Register here</a>
                    </p>
                </div>

                <!-- Login Type Selector -->
                <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-1 flex">
                    <button type="button" id="userLoginBtn" class="login-type-btn flex-1 py-2 px-4 rounded-md text-sm font-medium text-gray-700 bg-white active user-login-active text-white">
                        <i data-feather="user" class="h-4 w-4 inline mr-2"></i>
                        User Login
                    </button>
                    <button type="button" id="adminLoginBtn" class="login-type-btn flex-1 py-2 px-4 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700">
                        <i data-feather="shield" class="h-4 w-4 inline mr-2"></i>
                        Admin Login
                    </button>
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
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6" id="loginForm">
                            <div id="adminNotice" class="hidden bg-purple-100 border border-purple-400 text-purple-700 px-4 py-3 rounded">
                                <div class="flex items-center">
                                    <i data-feather="shield" class="h-4 w-4 mr-2"></i>
                                    <span class="text-sm">You are logging in as an administrator</span>
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email address
                                </label>
                                <div class="mt-1">
                                    <input id="email" name="email" type="email" autocomplete="email" required 
                                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm input-focus"
                                           placeholder="Enter your email address">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Password
                                </label>
                                <div class="mt-1">
                                    <input id="password" name="password" type="password" autocomplete="current-password" required 
                                           class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm input-focus"
                                           placeholder="Enter your password">
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                        Remember me
                                    </label>
                                </div>

                                <div class="text-sm">
                                    <a href="#" class="font-medium text-blue-600 hover:text-blue-500">
                                        Forgot your password?
                                    </a>
                                </div>
                            </div>

                            <div>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="admin_login" id="adminLoginFlag" value="false">
                                <button type="submit" id="submitBtn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                                    <i data-feather="log-in" class="mr-2 h-4 w-4"></i>
                                    Sign in as User
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
                                <span class="px-2 bg-gray-50 text-gray-500">Quick Demo Access</span>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">Demo Credentials</h4>
                            <p class="text-xs text-blue-700 mb-3">
                                Use these credentials for testing:
                            </p>
                            <div class="text-xs text-blue-800 space-y-1 mb-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div><strong>User:</strong> user@example.com</div>
                                    <div><strong>Admin:</strong> admin@ngcdf.go.ke</div>
                                    <div class="col-span-2"><strong>Password:</strong> password123</div>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="fillUserCredentials()" class="flex-1 text-xs bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded transition duration-300 flex items-center justify-center">
                                    <i data-feather="user" class="h-3 w-3 mr-1"></i>
                                    User Demo
                                </button>
                                <button onclick="fillAdminCredentials()" class="flex-1 text-xs bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded transition duration-300 flex items-center justify-center">
                                    <i data-feather="shield" class="h-3 w-3 mr-1"></i>
                                    Admin Demo
                                </button>
                            </div>
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
                        <h3 class="ml-3 text-3xl font-bold text-gray-800">Welcome Back</h3>
                    </div>
                    <p class="text-gray-600 mb-6 text-lg">
                        Efficient boardroom management for NG-CDF operations. Book meeting spaces with ease and confidence.
                    </p>
                    <div class="space-y-4 mb-8">
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
                    </div>
                    
                    <!-- Single Register Link -->
                    <div class="pt-6 border-t border-gray-200">
                        <p class="text-sm text-gray-600 mb-4 text-center">Ready to get started?</p>
                        <a href="register.php" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                            <i data-feather="user-plus" class="mr-2 h-4 w-4"></i>
                            Create Your Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        AOS.init();
        feather.replace();

        let isAdminLogin = false;
        const userLoginBtn = document.getElementById('userLoginBtn');
        const adminLoginBtn = document.getElementById('adminLoginBtn');
        const adminNotice = document.getElementById('adminNotice');
        const adminLoginFlag = document.getElementById('adminLoginFlag');
        const submitBtn = document.getElementById('submitBtn');

        function setLoginType(adminMode) {
            isAdminLogin = adminMode;
            
            if (adminMode) {
                // Admin mode
                userLoginBtn.classList.remove('active', 'user-login-active', 'text-white');
                userLoginBtn.classList.add('text-gray-500');
                adminLoginBtn.classList.add('active', 'admin-login-active', 'text-white');
                adminLoginBtn.classList.remove('text-gray-500');
                
                adminNotice.classList.remove('hidden');
                adminLoginFlag.value = 'true';
                submitBtn.innerHTML = '<i data-feather="shield" class="mr-2 h-4 w-4"></i>Sign in as Admin';
                submitBtn.className = submitBtn.className.replace('bg-blue-600 hover:bg-blue-700', 'bg-purple-600 hover:bg-purple-700');
            } else {
                // User mode
                adminLoginBtn.classList.remove('active', 'admin-login-active', 'text-white');
                adminLoginBtn.classList.add('text-gray-500');
                userLoginBtn.classList.add('active', 'user-login-active', 'text-white');
                userLoginBtn.classList.remove('text-gray-500');
                
                adminNotice.classList.add('hidden');
                adminLoginFlag.value = 'false';
                submitBtn.innerHTML = '<i data-feather="log-in" class="mr-2 h-4 w-4"></i>Sign in as User';
                submitBtn.className = submitBtn.className.replace('bg-purple-600 hover:bg-purple-700', 'bg-blue-600 hover:bg-blue-700');
            }
            feather.replace();
        }

        function fillAdminCredentials() {
            document.getElementById('email').value = 'admin@ngcdf.go.ke';
            document.getElementById('password').value = 'password123';
            setLoginType(true);
        }

        function fillUserCredentials() {
            document.getElementById('email').value = 'user@example.com';
            document.getElementById('password').value = 'password123';
            setLoginType(false);
        }

        // Event listeners
        userLoginBtn.addEventListener('click', () => setLoginType(false));
        adminLoginBtn.addEventListener('click', () => setLoginType(true));

        // Initialize
        setLoginType(false);
    </script>
</body>
</html>