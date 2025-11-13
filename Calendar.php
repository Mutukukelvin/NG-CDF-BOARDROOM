<?php
// calendar.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

// Fetch bookings for calendar
$bookings = [];
$user_id = $_SESSION['user_id'];

// Different query based on user role
if ($_SESSION['role'] == 'admin') {
    $sql = "SELECT b.*, u.username, r.name as room_name, r.location 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN rooms r ON b.room_id = r.id 
            ORDER BY b.booking_date, b.time_slot";
} else {
    $sql = "SELECT b.*, r.name as room_name, r.location 
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.id 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date, b.time_slot";
}

$stmt = $conn->prepare($sql);
if ($_SESSION['role'] != 'admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

while($booking = $result->fetch_assoc()) {
    $bookings[] = $booking;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | NG-CDF Boardroom</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <div class="min-h-screen pt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h1 class="text-2xl font-bold text-gray-900">Booking Calendar</h1>
                    <p class="text-gray-600">View all your scheduled meetings and events</p>
                </div>
                <div class="p-6">
                    <div id="calendar" class="h-96"></div>
                </div>
            </div>

            <!-- Upcoming Events List -->
            <div class="mt-8 bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Upcoming Events</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php if (!empty($bookings)): ?>
                            <?php 
                            $upcoming = array_filter($bookings, function($booking) {
                                return strtotime($booking['booking_date']) >= strtotime(date('Y-m-d'));
                            });
                            $upcoming = array_slice($upcoming, 0, 10);
                            ?>
                            
                            <?php foreach ($upcoming as $booking): ?>
                            <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($booking['event_name']) ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <?= date('M j, Y', strtotime($booking['booking_date'])) ?> • 
                                        <?= $booking['time_slot'] ?> • 
                                        <?= htmlspecialchars($booking['room_name']) ?>
                                    </p>
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <p class="text-xs text-gray-500">Booked by: <?= htmlspecialchars($booking['username']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                                    Confirmed
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No upcoming events found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($bookings as $booking): ?>
                    {
                        title: '<?= addslashes($booking['event_name']) ?> - <?= addslashes($booking['room_name']) ?>',
                        start: '<?= $booking['booking_date'] ?>',
                        extendedProps: {
                            timeSlot: '<?= $booking['time_slot'] ?>',
                            location: '<?= addslashes($booking['location']) ?>',
                            attendees: <?= $booking['attendees'] ?? 0 ?>
                        },
                        backgroundColor: '#3b82f6',
                        borderColor: '#3b82f6'
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    const event = info.event;
                    alert(
                        'Event: ' + event.title + '\n' +
                        'Date: ' + event.start.toLocaleDateString() + '\n' +
                        'Time: ' + event.extendedProps.timeSlot + '\n' +
                        'Location: ' + event.extendedProps.location + '\n' +
                        'Attendees: ' + event.extendedProps.attendees
                    );
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>