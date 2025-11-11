<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

// Fetch rooms from DB
$rooms = [];
$rooms_sql = "SELECT id, name, location, floor, capacity, equipment, description, image_url FROM rooms ORDER BY name";
if ($res = $conn->query($rooms_sql)) {
    while ($r = $res->fetch_assoc()) {
        $rooms[] = $r;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boardrooms | NG-CDF Booking</title>
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
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="font-sans antialiased bg-gray-50">
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Available Boardrooms</h1>
                    <p class="mt-1 text-sm text-gray-500">Select a boardroom to check availability and make a booking</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-feather="search" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Search boardrooms...">
                    </div>
                </div>
            </div>

            <!-- Boardrooms Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (empty($rooms)): ?>
                    <div class="col-span-full bg-white p-6 rounded shadow text-center">No rooms found.</div>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                    <div class="bg-white overflow-hidden shadow rounded-lg room-card" data-aos="fade-up">
                        <div class="h-48 overflow-hidden">
                            <!-- use local cdfpicture.jpg as requested -->
                            <img class="w-full h-full object-cover room-image" src="cdfpicture.jpg" alt="<?= htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($room['location'] . ' • ' . $room['floor'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-3 text-sm text-gray-700"><?= htmlspecialchars($room['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-gray-600">Capacity: <?= (int)$room['capacity'] ?></div>
                                <a href="booking.php?room_id=<?= (int)$room['id'] ?>" class="bg-blue-600 text-white px-3 py-2 rounded text-sm">Book</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">NG-CDF Boardrooms</h3>
                    <p class="text-gray-300 text-sm">
                        The official boardroom booking system for NG-CDF, providing efficient management of meeting spaces across all locations.
                    </p>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white text-sm">Home</a></li>
                        <li><a href="bookings.php" class="text-gray-300 hover:text-white text-sm">My Bookings</a></li>
                        <li><a href="rooms.php" class="text-gray-300 hover:text-white text-sm">Boardrooms</a></li>
                        <li><a href="booking.php" class="text-gray-300 hover:text-white text-sm">New Booking</a></li>
                        <li><a href="logout.php" class="text-gray-300 hover:text-white text-sm">Logout</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2 text-gray-300 text-sm">
                        <li class="flex items-center">
                            <i data-feather="mail" class="h-4 w-4 mr-2"></i>
                            support@ngcdf.go.ke
                        </li>
                        <li class="flex items-center">
                            <i data-feather="phone" class="h-4 w-4 mr-2"></i>
                            +254 20 123 4567 (Ext. 123)
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-700 text-center">
                <p class="text-gray-400 text-sm">
                    &copy; 2025 NG-CDF Boardroom Booking System. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        AOS.init();
        feather.replace();
    </script>
</body>
</html>