<?php
session_start();
include 'db_connect.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// Fetch available rooms
$rooms = [];
$rooms_sql = "SELECT * FROM rooms";
$rooms_result = $conn->query($rooms_sql);
if ($rooms_result->num_rows > 0) {
    while($room = $rooms_result->fetch_assoc()) {
        $rooms[] = $room;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission.";
    } else {
        $userId = $_SESSION['user_id'];
        $roomId = intval($_POST['room_id']);
        $eventName = trim($_POST['event-name']);
        $eventDescription = trim($_POST['event-description']);
        $bookingDate = $_POST['booking-date'];
        $timeSlot = $_POST['time-slot'];
        $equipment = isset($_POST['equipment']) ? implode(", ", $_POST['equipment']) : 'None';
        $attendees = intval($_POST['attendees']);
        $specialRequests = trim($_POST['special-requests']);
        
        // Check for required fields
        if (empty($eventName) || empty($bookingDate) || empty($timeSlot) || $roomId == 0) {
            $error = "Event name, room, date, and time slot are required.";
        } else {
            // Check for booking conflicts (status column exists as confirmed)
            $conflict_sql = "SELECT id FROM bookings WHERE room_id = ? AND booking_date = ? AND time_slot = ? AND status IN ('Pending', 'Approved')";
            $conflict_stmt = $conn->prepare($conflict_sql);
            $conflict_stmt->bind_param("iss", $roomId, $bookingDate, $timeSlot);
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();
            
            if ($conflict_result->num_rows > 0) {
                $error = "Sorry, this time slot is already booked for the selected room. Please choose a different time or room.";
            } else {
                // Insert booking with status (status column exists)
                $sql = "INSERT INTO bookings (user_id, room_id, event_name, event_description, booking_date, time_slot, equipment, attendees, special_requests, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisssssis", $userId, $roomId, $eventName, $eventDescription, $bookingDate, $timeSlot, $equipment, $attendees, $specialRequests);
                
                if ($stmt->execute()) {
                    $message = "Booking request submitted successfully! You will be redirected shortly.";
                    header("refresh:3;url=bookings.php");
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $conflict_stmt->close();
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
    <title>New Booking | NG-CDF Boardroom</title>
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
        .booking-section {
            background: linear-gradient(135deg, rgba(249,250,251,0.95) 0%, rgba(243,244,246,0.95) 100%);
            border-radius: 0.75rem;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
            padding: 1rem; /* reduced */
            max-width: 520px; /* narrower */
            margin: 0.5rem auto; /* smaller vertical margin */
        }
        .booking-section h2 {
            color: #1F2937;
            font-size: 1.125rem; /* text-xl */
            margin-bottom: 0.5rem;
        }
        .form-input {
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            padding: 0.4rem; /* smaller */
            transition: border-color 0.2s;
            font-size: 0.85rem; /* slightly smaller */
        }
        .form-input:focus {
            border-color: #3B82F6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
        }
        .submit-button {
            background-color: #3B82F6;
            color: white;
            padding: 0.4rem 0.8rem; /* reduced */
            border-radius: 0.375rem;
            transition: background-color 0.2s;
            font-size: 0.9rem;
        }
        .submit-button:hover { background-color: #2563EB; }
        /* compact spacing for the form */
        .compact-form .form-row { margin-bottom: 0.5rem; }
        .compact-form label { font-size: 0.8rem; }
        
        /* time-slot button styles */
        .time-slot-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid #D1D5DB;
            background: #ffffff;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background-color .12s, border-color .12s, color .12s;
        }
        .time-slot-btn:hover { border-color: #93C5FD; }
        .time-slot-btn.selected { background: #2563EB; color: #fff; border-color: #2563EB; }
        .time-slot-btn.booked { 
            opacity: 0.45; 
            cursor: not-allowed; 
            pointer-events: auto; /* Allow hover */
        }
        .time-slot-btn.booked:hover { 
            border-color: #EF4444;
            background-color: #FEF2F2;
        }
        
        /* Required field asterisk */
        .required-field::after {
            content: " *";
            color: #EF4444;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>
    <main class="container mx-auto px-4 py-6"> <!-- reduced vertical padding -->
        <section class="booking-section rounded-lg shadow-lg" data-aos="fade-up">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">New Booking</h2>
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $message ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $error ?></span>
                </div>
            <?php endif; ?>
            <form action="booking.php" method="POST" class="compact-form"> <!-- compact form class -->
                <div class="form-row">
                    <label for="room_id" class="block text-sm font-medium text-gray-700 required-field">Select Boardroom</label>
                    <select id="room_id" name="room_id" required class="form-input mt-1 block w-full">
                        <option value="" <?= $room_id == 0 ? 'selected' : '' ?>>Select a boardroom</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" <?= $room_id == $room['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($room['location']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="event-name" class="block text-sm font-medium text-gray-700 required-field">Event Name</label>
                    <input type="text" id="event-name" name="event-name" required class="form-input mt-1 block w-full">
                </div>
                <div class="form-row">
                    <label for="event-description" class="block text-sm font-medium text-gray-700">Event Description</label>
                    <textarea id="event-description" name="event-description" rows="1" class="form-input mt-1 block w-full"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2"> <!-- tighter grid gap -->
                    <div class="form-row">
                        <label for="booking-date" class="block text-sm font-medium text-gray-700 required-field">Date</label>
                        <input type="date" id="booking-date" name="booking-date" min="<?= date('Y-m-d') ?>" required class="form-input mt-1 block w-full">
                    </div>
                    <div class="form-row">
                        <label for="time-slot" class="block text-sm font-medium text-gray-700 required-field">Time Slot</label>
                        <div id="time-slot-container" class="mt-1 grid grid-cols-3 gap-2"></div>
                        <!-- hidden input that will carry chosen slot to the server -->
                        <input type="hidden" id="time-slot" name="time-slot" value="" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="block text-sm font-medium text-gray-700">Equipment</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <label class="inline-flex items-center text-sm">
                            <input id="projector" name="equipment[]" type="checkbox" value="Projector" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-gray-900">Projector</span>
                        </label>
                        <label class="inline-flex items-center text-sm">
                            <input id="whiteboard" name="equipment[]" type="checkbox" value="Whiteboard" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-gray-900">Whiteboard</span>
                        </label>
                        <label class="inline-flex items-center text-sm">
                            <input id="video-conference" name="equipment[]" type="checkbox" value="Video Conference System" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-gray-900">Video Conference</span>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label for="attendees" class="block text-sm font-medium text-gray-700">Attendees</label>
                    <input type="number" id="attendees" name="attendees" min="1" class="form-input mt-1 block w-full">
                </div>
                <div class="form-row">
                    <label for="special-requests" class="block text-sm font-medium text-gray-700">Special Requests</label>
                    <textarea id="special-requests" name="special-requests" rows="1" class="form-input mt-1 block w-full"></textarea>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="flex justify-end mt-2">
                    <button type="submit" class="submit-button">
                        Book
                    </button>
                </div>
            </form>
        </section>
    </main>
    <footer class="bg-gray-900 text-gray-400 py-8 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400 text-sm">
                &copy; 2025 NG-CDF Boardroom Booking System. All rights reserved.
            </p>
        </div>
    </footer>
    <script>
        AOS.init();
        feather.replace();
        
        // Set minimum date to today
        document.getElementById('booking-date').min = new Date().toISOString().split('T')[0];
        
        // ----- time-slot UI logic -----
        (function(){
            const timeSlots = ['9am-10am','10am-11am','11am-12pm','1pm-2pm','2pm-3pm','3pm-4pm'];
            const container = document.getElementById('time-slot-container');
            const hiddenInput = document.getElementById('time-slot');
            const roomSelect = document.getElementById('room_id');
            const dateInput = document.getElementById('booking-date');

            function renderButtons(booked = []) {
                container.innerHTML = '';
                timeSlots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'time-slot-btn';
                    btn.textContent = slot.replace('-', ' – ');
                    btn.dataset.slot = slot;
                    if (booked.includes(slot)) {
                        btn.classList.add('booked');
                        btn.title = 'Time Slot Booked'; // Corrected tooltip text
                    } else {
                        btn.addEventListener('click', () => {
                            // clear selected
                            container.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
                            btn.classList.add('selected');
                            hiddenInput.value = slot;
                        });
                    }
                    container.appendChild(btn);
                });
            }

            async function fetchBookedSlots() {
                const room = parseInt(roomSelect.value || 0, 10);
                const date = dateInput.value || '';
                // reset selection when room or date not chosen
                hiddenInput.value = '';
                if (!room || !date) {
                    renderButtons([]);
                    return;
                }
                try {
                    const form = new FormData();
                    form.append('room_id', room);
                    form.append('date', date);
                    const resp = await fetch('check_bookings.php', { method: 'POST', body: form });
                    const data = await resp.json();
                    const booked = Array.isArray(data.booked) ? data.booked : [];
                    renderButtons(booked);
                } catch (e) {
                    // on error just render empty (all available)
                    renderButtons([]);
                }
            }

            // initial render (no room/date yet)
            renderButtons([]);

            // update booked slots when room or date changes
            roomSelect.addEventListener('change', fetchBookedSlots);
            dateInput.addEventListener('change', fetchBookedSlots);

            // If a room is pre-selected from URL parameter, trigger the change event to load booked slots
            <?php if ($room_id > 0): ?>
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    roomSelect.dispatchEvent(new Event('change'));
                }, 100);
            <?php endif; ?>
        })();
    </script>
</body>
</html>