<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

// Handle actions (cancel/delete only)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'cancel' || $action == 'delete') {
        $sql = "DELETE FROM bookings WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: bookings.php");
    exit();
}

// Fetch user's bookings
$user_id = $_SESSION['user_id'];
$bookings = [];

// First, check if room_id column exists
$check_column_sql = "SHOW COLUMNS FROM bookings LIKE 'room_id'";
$column_result = $conn->query($check_column_sql);

if ($column_result->num_rows > 0) {
    // room_id column exists - use the new query
    $sql = "SELECT b.*, r.name as room_name, r.location, r.floor, r.capacity 
            FROM bookings b 
            LEFT JOIN rooms r ON b.room_id = r.id 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date DESC, b.created_at DESC";
} else {
    // room_id column doesn't exist - use fallback query
    $sql = "SELECT b.*, 'Harambee Boardroom' as room_name, 'Harambee Sacco Plaza' as location, '10th Floor' as floor, 20 as capacity
            FROM bookings b 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date DESC, b.created_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($booking = $result->fetch_assoc()) {
        $bookings[] = $booking;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | NG-CDF Boardroom</title>
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
    <style>
        .booking-card {
            transition: all 0.3s ease;
        }
        .booking-card:hover {
            background-color: #f9fafb;
        }
        .past-booking {
            opacity: 0.7;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50">
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">My Bookings</h1>
                    <p class="mt-1 text-sm text-gray-500">View and manage your boardroom bookings</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <a href="booking.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-feather="plus" class="mr-2 h-4 w-4"></i> New Booking
                    </a>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg p-8 text-center" data-aos="fade-up">
                    <i data-feather="calendar" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No bookings yet</h3>
                    <p class="text-gray-500 mb-4">You haven't made any boardroom bookings yet.</p>
                    <a href="booking.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        Make your first booking
                    </a>
                </div>
            <?php else: ?>
                <!-- Bookings List -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg" data-aos="fade-up">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($bookings as $booking): 
                            $is_past = strtotime($booking['booking_date']) < strtotime(date('Y-m-d'));
                            $booking_class = $is_past ? 'past-booking' : '';
                        ?>
                        <li class="booking-card <?= $booking_class ?>">
                            <div class="px-4 py-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-600 truncate"><?= htmlspecialchars($booking['event_name']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($booking['room_name']) ?> (<?= htmlspecialchars($booking['location']) ?>, <?= htmlspecialchars($booking['floor']) ?>)</p>
                                    </div>
                                    <div class="ml-2 flex-shrink-0 flex">
                                        <?php if ($is_past): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <i data-feather="check-circle" class="mr-1 h-3 w-3"></i> Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <i data-feather="check-circle" class="mr-1 h-3 w-3"></i> Confirmed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2 sm:flex sm:justify-between">
                                    <div class="sm:flex">
                                        <p class="flex items-center text-sm text-gray-500">
                                            <i data-feather="calendar" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"></i>
                                            <?= date('M j, Y', strtotime($booking['booking_date'])) ?> • <?= $booking['time_slot'] ?>
                                        </p>
                                        <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0 sm:ml-6">
                                            <i data-feather="users" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"></i>
                                            <?= $booking['attendees'] ?> attendees
                                        </p>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                        <i data-feather="clock" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"></i>
                                        Booked on <?= date('M j, Y', strtotime($booking['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($booking['equipment']) && $booking['equipment'] != 'None'): ?>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <?php 
                                    $equipment_list = explode(', ', $booking['equipment']);
                                    foreach ($equipment_list as $equip): 
                                        if (!empty(trim($equip))):
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i data-feather="<?= $equip == 'Projector' ? 'video' : ($equip == 'Whiteboard' ? 'edit-3' : 'monitor') ?>" class="mr-1 h-3 w-3"></i>
                                        <?= htmlspecialchars($equip) ?>
                                    </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($booking['special_requests'])): ?>
                                <div class="mt-3">
                                    <p class="text-sm text-gray-600"><strong>Special Requests:</strong> <?= htmlspecialchars($booking['special_requests']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4 flex justify-end space-x-3">
                                    <?php if (!$is_past): ?>
                                        <a href="bookings.php?action=cancel&id=<?= $booking['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.')" 
                                           class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <i data-feather="x" class="mr-1 h-4 w-4"></i> Cancel Booking
                                        </a>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500 italic">This booking has been completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Quick Stats -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6" data-aos="fade-up" data-aos-delay="100">
                    <?php
                    $total_bookings = count($bookings);
                    $upcoming_bookings = 0;
                    $past_bookings = 0;
                    
                    foreach ($bookings as $booking) {
                        if (strtotime($booking['booking_date']) >= strtotime(date('Y-m-d'))) {
                            $upcoming_bookings++;
                        } else {
                            $past_bookings++;
                        }
                    }
                    ?>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="calendar" class="h-6 w-6 text-blue-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Total Bookings</h3>
                                <p class="text-2xl font-bold text-blue-600"><?= $total_bookings ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="clock" class="h-6 w-6 text-green-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Upcoming</h3>
                                <p class="text-2xl font-bold text-green-600"><?= $upcoming_bookings ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i data-feather="check-circle" class="h-6 w-6 text-purple-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Completed</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= $past_bookings ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        AOS.init();
        feather.replace();
        
        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to booking cards
            const bookingCards = document.querySelectorAll('.booking-card');
            bookingCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });
            
            // Add confirmation for cancel actions
            const cancelLinks = document.querySelectorAll('a[href*="action=cancel"]');
            cancelLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>