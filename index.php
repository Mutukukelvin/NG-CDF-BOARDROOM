<?php
session_start();
include 'db_connect.php';

$upcoming = [];
$stats = ['total_bookings' => 0, 'upcoming' => 0];

if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
    $today = date('Y-m-d');

    // Fetch next 5 upcoming bookings for the user
    $sql = "SELECT b.event_name, b.booking_date, b.time_slot, r.name AS room_name
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.user_id = ? AND b.booking_date >= ?
            ORDER BY b.booking_date ASC, b.time_slot ASC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $upcoming[] = $row;
    }
    $stmt->close();

    // Quick stats for the user
    $stats_sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN booking_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming
                  FROM bookings
                  WHERE user_id = ?";
    $s = $conn->prepare($stats_sql);
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result();
    if ($r = $sr->fetch_assoc()) {
        $stats['total_bookings'] = (int)$r['total'];
        $stats['upcoming'] = (int)$r['upcoming'];
    }
    $s->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | NG-CDF Boardroom</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>

    <main class="min-h-screen">
        <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Logged In User Dashboard -->
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Welcome Section -->
                <div class="mb-8" data-aos="fade-up">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </h1>
                    <p class="text-gray-600">Manage your boardroom bookings and explore available rooms</p>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" data-aos="fade-up" data-aos-delay="100">
                    <div class="bg-white rounded-lg shadow-md p-6 stats-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="plus" class="h-6 w-6 text-blue-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">New Booking</h3>
                                <p class="text-sm text-gray-500">Book a boardroom for your meeting</p>
                                <a href="booking.php" class="inline-block mt-2 text-blue-600 hover:text-blue-700 text-sm font-medium">
                                    Book Now →
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 stats-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="calendar" class="h-6 w-6 text-green-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">My Bookings</h3>
                                <p class="text-sm text-gray-500">View and manage your bookings</p>
                                <a href="bookings.php" class="inline-block mt-2 text-green-600 hover:text-green-700 text-sm font-medium">
                                    View Bookings →
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 stats-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="home" class="h-6 w-6 text-purple-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Available Rooms</h3>
                                <p class="text-sm text-gray-500">Explore all boardrooms</p>
                                <a href="rooms.php" class="inline-block mt-2 text-purple-600 hover:text-purple-700 text-sm font-medium">
                                    View Rooms →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" data-aos="fade-up" data-aos-delay="200">
                    <!-- Upcoming Bookings -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900">Upcoming Bookings</h2>
                            <a href="bookings.php" class="text-blue-600 hover:text-blue-700 text-sm">View All</a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($upcoming as $booking): ?>
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($booking['event_name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($booking['booking_date'] . ' ' . $booking['time_slot']) ?></p>
                                    <p class="text-sm text-gray-600">Room: <?= htmlspecialchars($booking['room_name']) ?></p>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                    Confirmed
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Stats</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Total Bookings</span>
                                <span class="font-semibold text-gray-900"><?= $stats['total_bookings'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Upcoming</span>
                                <span class="font-semibold text-blue-600"><?= $stats['upcoming'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Favourite Room</span>
                                <span class="font-semibold text-purple-600">Harambee</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Grid -->
                <div class="mt-12" data-aos="fade-up" data-aos-delay="300">
                    <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">Why Choose Our Boardroom System?</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-feather="clock" class="h-8 w-8 text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Real-time Availability</h3>
                            <p class="text-gray-600">See which boardrooms are available in real-time and book instantly.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-feather="check-circle" class="h-8 w-8 text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Instant Confirmation</h3>
                            <p class="text-gray-600">Get immediate confirmation for your bookings with all details.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-feather="settings" class="h-8 w-8 text-purple-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Easy Management</h3>
                            <p class="text-gray-600">Manage, modify, or cancel your bookings with just a few clicks.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Public Homepage (Non-logged in users) -->
        <div class="container mx-auto px-4 py-8">
            <section class="hero-section bg-gray-800 text-white rounded-lg shadow-lg p-16 text-center" data-aos="fade-up">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Boardroom Booking System</h1>
                <p class="text-xl mb-8">Efficiently manage and book boardrooms for your meetings and events.</p>
                <div class="flex justify-center space-x-4">
                    <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full transition duration-300">Login</a>
                    <a href="register.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-full transition duration-300">Register Now</a>
                </div>
            </section>

            <!-- Additional Content for Non-Logged In Users -->
            <section class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-8" data-aos="fade-up">
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-feather="user-plus" class="h-6 w-6 text-blue-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Create Account</h3>
                    <p class="text-gray-600">Register for free to access our boardroom booking system</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-feather="calendar" class="h-6 w-6 text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Book Instantly</h3>
                    <p class="text-gray-600">Reserve boardrooms with real-time availability</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-feather="check-circle" class="h-6 w-6 text-purple-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Get Confirmed</h3>
                    <p class="text-gray-600">Receive instant confirmation for your bookings</p>
                </div>
            </section>

            <section class="mt-12 bg-blue-50 rounded-lg p-8 text-center" data-aos="fade-up">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Ready to Get Started?</h2>
                <p class="text-gray-600 mb-6">Join hundreds of professionals already using our booking system</p>
                <a href="register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition duration-300 inline-flex items-center">
                    <i data-feather="user-plus" class="mr-2 h-5 w-5"></i>
                    Create Your Account
                </a>
            </section>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer - Only show for non-logged in users -->
    <?php if (!isset($_SESSION['user_id'])): ?>
    <footer class="bg-gray-900 text-gray-400 py-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">NG-CDF Boardroom</h3>
                    <p class="text-sm">Providing seamless booking solutions for your professional needs.</p>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white text-sm">Home</a></li>
                        <li><a href="login.php" class="text-gray-300 hover:text-white text-sm">Login</a></li>
                        <li><a href="register.php" class="text-gray-300 hover:text-white text-sm">Register</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2 text-gray-300 text-sm">
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-mail h-4 w-4 mr-2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            support@ngcdf.go.ke
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-phone h-4 w-4 mr-2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2.18A19.09 19.09 0 0 1 8.64 3.06 20.05 20.05 0 0 1 3 13.56 18.09 18.09 0 0 1 1.82 20.25 2 2 0 0 1 3.5 22h3a2 2 0 0 0 2 2 17.5 17.5 0 0 0 9.5-3.5 2 2 0 0 0 2-2v-3z"></path></svg>
                            +254 20 123 4567 (Ext. 123)
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-700 text-center">
                <p class="text-gray-400 text-sm">
                    &copy; 2023 NG-CDF Boardroom Booking System. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <script>
        AOS.init();
        feather.replace();
    </script>
</body>
</html>