<style>
    /* central navbar height and spacer */
    :root { --navbar-height: 64px; }
    @media (max-width: 640px) { :root { --navbar-height: 56px; } }
    .navbar-spacer { height: var(--navbar-height); width: 100%; }
</style>

<nav class="bg-white shadow-lg fixed w-full top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-8 w-8" src="cdfpicture.jpg" alt="NG-CDF Logo">
                    <span class="ml-2 text-xl font-bold text-blue-600">NG-CDF Boardrooms</span>
                </div>
            </div>
            
            <div class="hidden sm:ml-6 sm:flex sm:items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="admin_dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium <?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'border-b-2 border-blue-600' : '' ?>">Admin Dashboard</a>
                    <?php endif; ?>
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'border-b-2 border-blue-600' : '' ?>">Home</a>
                    <a href="bookings.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium <?= basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'border-b-2 border-blue-600' : '' ?>">My Bookings</a>
                    <a href="rooms.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium <?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'border-b-2 border-blue-600' : '' ?>">Rooms</a>
                    <a href="booking.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium <?= basename($_SERVER['PHP_SELF']) == 'booking.php' ? 'border-b-2 border-blue-600' : '' ?>">New Booking</a>
                    
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-300">
                        <i data-feather="log-out" class="w-4 h-4 inline mr-1"></i>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Home</a>
                    <a href="login.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Login</a>
                    <a href="register.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-300">Register</a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile menu button -->
            <div class="sm:hidden flex items-center">
                <button onclick="toggleMobileMenu()" class="p-2 rounded-md text-gray-500 hover:text-gray-700">
                    <i data-feather="menu" class="h-6 w-6"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div id="mobile-menu" class="sm:hidden hidden bg-white shadow-lg">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="admin_dashboard.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Admin Dashboard</a>
                <?php endif; ?>
                <a href="index.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Home</a>
                <a href="bookings.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">My Bookings</a>
                <a href="rooms.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Rooms</a>
                <a href="booking.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">New Booking</a>
                <a href="logout.php" class="block px-3 py-2 text-red-600 hover:text-red-800">Logout</a>
            <?php else: ?>
                <a href="index.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Home</a>
                <a href="login.php" class="block px-3 py-2 text-gray-700 hover:text-blue-600">Login</a>
                <a href="register.php" class="block px-3 py-2 text-blue-600 hover:text-blue-800">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Spacer to offset fixed navbar -->
<div class="navbar-spacer" aria-hidden="true"></div>

<script>
function toggleMobileMenu() {
    document.getElementById('mobile-menu').classList.toggle('hidden');
}

// Initialize icons
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});
</script>