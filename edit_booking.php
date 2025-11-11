<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$message = '';

// Fetch booking details
$booking = null;
$sql = "SELECT b.*, r.name as room_name FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ? AND b.user_id = ? AND b.status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Booking not found or cannot be edited.";
} else {
    $booking = $result->fetch_assoc();
}

// Fetch available rooms
$rooms = [];
$rooms_sql = "SELECT * FROM rooms";
$rooms_result = $conn->query($rooms_sql);
if ($rooms_result->num_rows > 0) {
    while($room = $rooms_result->fetch_assoc()) {
        $rooms[] = $room;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $booking) {
    $eventName = trim($_POST['event-name']);
    $eventDescription = trim($_POST['event-description']);
    $bookingDate = $_POST['booking-date'];
    $timeSlot = $_POST['time-slot'];
    $equipment = isset($_POST['equipment']) ? implode(", ", $_POST['equipment']) : 'None';
    $attendees = intval($_POST['attendees']);
    $specialRequests = trim($_POST['special-requests']);
    
    if (empty($eventName) || empty($bookingDate) || empty($timeSlot)) {
        $error = "Event name, date, and time slot are required.";
    } else {
        // Check for booking conflicts (excluding current booking)
        $conflict_sql = "SELECT id FROM bookings WHERE room_id = ? AND booking_date = ? AND time_slot = ? AND id != ? AND status IN ('Pending', 'Approved')";
        $conflict_stmt = $conn->prepare($conflict_sql);
        $conflict_stmt->bind_param("issi", $booking['room_id'], $bookingDate, $timeSlot, $booking_id);
        $conflict_stmt->execute();
        $conflict_result = $conflict_stmt->get_result();
        
        if ($conflict_result->num_rows > 0) {
            $error = "Sorry, this time slot is already booked. Please choose a different time.";
        } else {
            $update_sql = "UPDATE bookings SET event_name = ?, event_description = ?, booking_date = ?, time_slot = ?, equipment = ?, attendees = ?, special_requests = ? WHERE id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssisii", $eventName, $eventDescription, $bookingDate, $timeSlot, $equipment, $attendees, $specialRequests, $booking_id, $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                $message = "Booking updated successfully!";
                header("refresh:2;url=bookings.php");
            } else {
                $error = "Error updating booking: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
        $conflict_stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking | NG-CDF Boardroom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    <div class="min-h-screen pt-16"> <!-- add top padding to avoid fixed navbar overlap -->
        <!-- Main Content -->
        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Edit Booking</h2>
                    </div>
                    
                    <div class="p-6">
                        <?php if ($error): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                <?= $message ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking): ?>
                        <form action="edit_booking.php?id=<?= $booking_id ?>" method="POST" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Room</label>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($booking['room_name']) ?></p>
                                <p class="text-xs text-gray-500">Room cannot be changed for existing bookings</p>
                            </div>
                            
                            <div>
                                <label for="event-name" class="block text-sm font-medium text-gray-700">Event Name *</label>
                                <input type="text" id="event-name" name="event-name" value="<?= htmlspecialchars($booking['event_name']) ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                            </div>
                            
                            <div>
                                <label for="event-description" class="block text-sm font-medium text-gray-700">Event Description</label>
                                <textarea id="event-description" name="event-description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"><?= htmlspecialchars($booking['event_description']) ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="booking-date" class="block text-sm font-medium text-gray-700">Date of Booking *</label>
                                    <input type="date" id="booking-date" name="booking-date" value="<?= $booking['booking_date'] ?>" min="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                                </div>
                                <div>
                                    <label for="time-slot" class="block text-sm font-medium text-gray-700">Time Slot *</label>
                                    <select id="time-slot" name="time-slot" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                                        <option value="">Select a time slot</option>
                                        <option value="9am-10am" <?= $booking['time_slot'] == '9am-10am' ? 'selected' : '' ?>>9am - 10am</option>
                                        <option value="10am-11am" <?= $booking['time_slot'] == '10am-11am' ? 'selected' : '' ?>>10am - 11am</option>
                                        <option value="11am-12pm" <?= $booking['time_slot'] == '11am-12pm' ? 'selected' : '' ?>>11am - 12pm</option>
                                        <option value="1pm-2pm" <?= $booking['time_slot'] == '1pm-2pm' ? 'selected' : '' ?>>1pm - 2pm</option>
                                        <option value="2pm-3pm" <?= $booking['time_slot'] == '2pm-3pm' ? 'selected' : '' ?>>2pm - 3pm</option>
                                        <option value="3pm-4pm" <?= $booking['time_slot'] == '3pm-4pm' ? 'selected' : '' ?>>3pm - 4pm</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Additional Equipment Needed</label>
                                <div class="mt-2 space-y-2">
                                    <?php
                                    $current_equipment = explode(', ', $booking['equipment']);
                                    ?>
                                    <div class="flex items-center">
                                        <input id="projector" name="equipment[]" type="checkbox" value="Projector" <?= in_array('Projector', $current_equipment) ? 'checked' : '' ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <label for="projector" class="ml-2 block text-sm text-gray-900">Projector</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="whiteboard" name="equipment[]" type="checkbox" value="Whiteboard" <?= in_array('Whiteboard', $current_equipment) ? 'checked' : '' ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <label for="whiteboard" class="ml-2 block text-sm text-gray-900">Whiteboard</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-conference" name="equipment[]" type="checkbox" value="Video Conference System" <?= in_array('Video Conference System', $current_equipment) ? 'checked' : '' ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <label for="video-conference" class="ml-2 block text-sm text-gray-900">Video Conference System</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="attendees" class="block text-sm font-medium text-gray-700">Number of Attendees</label>
                                <input type="number" id="attendees" name="attendees" value="<?= $booking['attendees'] ?>" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
                            </div>
                            
                            <div>
                                <label for="special-requests" class="block text-sm font-medium text-gray-700">Special Requests</label>
                                <textarea id="special-requests" name="special-requests" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"><?= htmlspecialchars($booking['special_requests']) ?></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-6">
                                <a href="bookings.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                                    Cancel
                                </a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                                    Update Booking
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        feather.replace();
        // Set minimum date to today
        document.getElementById('booking-date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>