<?php
session_start();
require 'db.php';
<<<<<<< HEAD
require 'trip_segment_helper.php';
=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

<<<<<<< HEAD
function trip_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

travel_ensure_trip_tables($conn);

$destinationIdColumn = travel_get_destination_id_column($conn);
$transportModes = travel_get_transport_modes();
$stageOptions = travel_get_stage_options();
=======
function trip_escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
$tripId = (int) ($_GET['id'] ?? 0);

if ($tripId <= 0) {
    header("Location: index.php");
    exit();
}

<<<<<<< HEAD
$stmt = $conn->prepare(
    "SELECT
        t.*,
        i.dest_id,
        i.distance_km,
        i.transport_mode,
        i.trip_stage,
        i.sequence_order,
        d.name AS destination_name,
        d.eco_rating
    FROM trips t
    INNER JOIN itinerary_items i ON i.trip_id = t.trip_id
    LEFT JOIN destinations d ON d.{$destinationIdColumn} = i.dest_id
    WHERE t.trip_id = ? AND t.user_id = ?
    LIMIT 1"
);
$stmt->bind_param('ii', $tripId, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result ? $result->fetch_assoc() : null;
=======
$stmt = $conn->prepare("SELECT t.*, i.dest_id, i.distance_km, i.transport_mode FROM trips t LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id AND i.sequence_order = 1 WHERE t.trip_id = ? LIMIT 1");
$stmt->bind_param('i', $tripId);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
$stmt->close();

if (!$trip) {
    header("Location: index.php");
    exit();
}

<<<<<<< HEAD
$destId = (int) ($trip['dest_id'] ?? 0);
$destinationName = (string) ($trip['destination_name'] ?? 'Destination');

$formValues = [
    'trip_name' => $trip['trip_name'] ?? '',
    'origin' => $trip['origin'] ?? '',
    'trip_date' => travel_get_trip_date_value((string) ($trip['trip_date'] ?? ''), (string) ($trip['created_at'] ?? '')),
    'distance_km' => $trip['distance_km'] ?? '',
    'transport_mode' => $trip['transport_mode'] ?? 'train',
    'trip_stage' => $trip['trip_stage'] ?? 'to_destination',
    'sequence_order' => $trip['sequence_order'] ?? 1,
];

$formError = '';
$routeOrderHelpText = travel_build_next_order_help($conn, $destId, (string) $formValues['trip_date'], $tripId);
=======
$destId = (int) $trip['dest_id'];

$transportModes = [
    'bike' => ['label' => 'Bike', 'factor' => 0.00, 'eco' => true],
    'bus' => ['label' => 'Bus', 'factor' => 0.08, 'eco' => true],
    'train' => ['label' => 'Train', 'factor' => 0.04, 'eco' => true],
    'electric_vehicle' => ['label' => 'Electric Vehicle', 'factor' => 0.12, 'eco' => true],
    'flight' => ['label' => 'Flight', 'factor' => 0.25, 'eco' => false],
];

$formValues = [
    'trip_name' => $trip['trip_name'],
    'origin' => $trip['origin'],
    'distance_km' => $trip['distance_km'] ?? 0,
    'transport_mode' => $trip['transport_mode'] ?? 'train',
];

$formError = '';
$formSuccess = '';
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
<<<<<<< HEAD
    $formValues['trip_date'] = trim($_POST['trip_date'] ?? travel_get_default_trip_date());
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'train');
    $formValues['trip_stage'] = trim($_POST['trip_stage'] ?? 'to_destination');
    $formValues['sequence_order'] = trim($_POST['sequence_order'] ?? '');

    $tripDate = $formValues['trip_date'];
    $distanceKm = (float) $formValues['distance_km'];
    $sequenceOrder = $formValues['sequence_order'] === '' ? 0 : (int) $formValues['sequence_order'];
    $transportMode = $formValues['transport_mode'];
    $tripStage = $formValues['trip_stage'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $tripDate === '' || $distanceKm <= 0) {
        $formError = 'Please complete the from, to, trip date, and distance fields.';
    } elseif (!travel_is_valid_trip_date($tripDate)) {
        $formError = 'Please choose a valid trip date.';
    } elseif (!array_key_exists($transportMode, $transportModes)) {
        $formError = 'Invalid transport mode.';
    } elseif (!array_key_exists($tripStage, $stageOptions)) {
        $formError = 'Invalid trip stage.';
    } elseif ($formValues['sequence_order'] !== '' && $sequenceOrder <= 0) {
        $formError = 'Route order must be 1 or higher when you set it manually.';
    } else {
        if ($sequenceOrder <= 0) {
            $sequenceOrder = travel_get_next_sequence_order($conn, $destId, $tripStage, $tripId, $tripDate);
        }

        $calculation = travel_calculate_segment_result(
            ['eco_rating' => $trip['eco_rating'] ?? 3],
            $distanceKm,
            $transportMode
        );

        $updateStmt = $conn->prepare("UPDATE trips SET trip_name = ?, origin = ?, trip_date = ?, total_carbon_est = ? WHERE trip_id = ? AND user_id = ?");
        $updateStmt->bind_param('sssdii', $formValues['trip_name'], $formValues['origin'], $tripDate, $calculation['carbon_estimate'], $tripId, $_SESSION['user_id']);

        if ($updateStmt->execute()) {
            $updateStmt->close();

            $updateItemStmt = $conn->prepare("UPDATE itinerary_items SET distance_km = ?, transport_mode = ?, carbon_est = ?, is_eco_friendly = ?, trip_stage = ?, sequence_order = ? WHERE trip_id = ?");
            $updateItemStmt->bind_param(
                'dsdisii',
                $distanceKm,
                $transportMode,
                $calculation['carbon_estimate'],
                $calculation['is_eco_friendly'],
                $tripStage,
                $sequenceOrder,
                $tripId
            );
            $updateItemStmt->execute();
            $updateItemStmt->close();
            header("Location: destination_detail.php?id=" . urlencode((string) $destId) . "&trip_updated=1");
            exit();
        } else {
            $formError = 'Error updating the trip segment.';
            $updateStmt->close();
        }
    }
}

$routeOrderHelpText = travel_build_next_order_help($conn, $destId, (string) $formValues['trip_date'], $tripId);
=======
    $formValues['distance_km'] = (float) ($_POST['distance_km'] ?? 0);
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'train');

    if (empty($formValues['trip_name']) || empty($formValues['origin']) || $formValues['distance_km'] <= 0) {
        $formError = 'Please fill in all fields correctly.';
    } elseif (!array_key_exists($formValues['transport_mode'], $transportModes)) {
        $formError = 'Invalid transport mode.';
    } else {
        $modeConfig = $transportModes[$formValues['transport_mode']];
        $carbonEstimate = round($formValues['distance_km'] * $modeConfig['factor'], 2);

        $updateStmt = $conn->prepare("UPDATE trips SET trip_name = ?, origin = ?, total_carbon_est = ? WHERE trip_id = ?");
        $updateStmt->bind_param('ssdi', $formValues['trip_name'], $formValues['origin'], $carbonEstimate, $tripId);
        
        if ($updateStmt->execute()) {
            $updateItemStmt = $conn->prepare("UPDATE itinerary_items SET distance_km = ?, transport_mode = ?, carbon_est = ?, is_eco_friendly = ? WHERE trip_id = ?");
            $isEco = $modeConfig['eco'] ? 1 : 0;
            $updateItemStmt->bind_param('ddsii', $formValues['distance_km'], $formValues['transport_mode'], $carbonEstimate, $isEco, $tripId);
            $updateItemStmt->execute();
            $updateItemStmt->close();

            $formSuccess = 'Trip updated successfully!';
            $trip['trip_name'] = $formValues['trip_name'];
            $trip['origin'] = $formValues['origin'];
            $trip['distance_km'] = $formValues['distance_km'];
            $trip['transport_mode'] = $formValues['transport_mode'];
        } else {
            $formError = 'Error updating trip.';
        }
        $updateStmt->close();
    }
}
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
?>
<!DOCTYPE html>
<html>
<head>
<<<<<<< HEAD
    <title>Edit Trip Segment</title>
=======
    <title>Edit Trip</title>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
<<<<<<< HEAD
        <div class="container" style="max-width: 680px;">
            <header class="topbar">
                <div>
                    <h1>Edit Trip Segment</h1>
                    <p><?php echo trip_escape($destinationName); ?>: <?php echo trip_escape($trip['origin'] ?? ''); ?> to <?php echo trip_escape($trip['trip_name'] ?? ''); ?></p>
=======
        <div class="container" style="max-width: 600px;">
            <header class="topbar">
                <div>
                    <h1>Edit Trip</h1>
                    <p><?php echo trip_escape($trip['trip_name']); ?></p>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="destination_detail.php?id=<?php echo $destId; ?>">Back</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="panel card">
<<<<<<< HEAD
                <p class="helper-note">Each trip is one fare or one route leg under the destination goal.</p>

                <?php if ($formError !== ''): ?>
                    <p class="message error"><?php echo trip_escape($formError); ?></p>
                <?php endif; ?>

                <form method="POST" class="planner-form trip-segment-form">
                    <div class="field-grid trip-segment-grid">
                        <div class="field">
                            <label for="origin">From</label>
                            <input type="text" id="origin" name="origin" placeholder="Your location" value="<?php echo trip_escape($formValues['origin']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="trip_name">To</label>
                            <input type="text" id="trip_name" name="trip_name" placeholder="Next stop" value="<?php echo trip_escape($formValues['trip_name']); ?>" required>
                        </div>

                        <div class="field">
                            <label for="trip_stage">Trip Stage</label>
                            <select id="trip_stage" name="trip_stage" required>
                                <?php foreach ($stageOptions as $key => $data): ?>
                                    <option value="<?php echo trip_escape($key); ?>" <?php echo $formValues['trip_stage'] === $key ? 'selected' : ''; ?>>
                                        <?php echo trip_escape($data['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="distance_km">Distance in km</label>
                            <input type="number" id="distance_km" name="distance_km" min="0.1" step="0.1" value="<?php echo trip_escape($formValues['distance_km']); ?>" required>
                        </div>

                        <div class="field">
=======
                <?php if ($formError): ?>
                    <p class="message error"><?php echo trip_escape($formError); ?></p>
                <?php endif; ?>

                <?php if ($formSuccess): ?>
                    <p class="message success"><?php echo trip_escape($formSuccess); ?></p>
                <?php endif; ?>

                <form method="POST" class="planner-form">
                    <div class="field-grid">
                        <div class="field field-full">
                            <label for="trip_name">Trip Name</label>
                            <input type="text" id="trip_name" name="trip_name" placeholder="My eco trip" value="<?php echo trip_escape($formValues['trip_name']); ?>" required>
                        </div>

                        <div class="field field-full">
                            <label for="origin">Origin</label>
                            <input type="text" id="origin" name="origin" placeholder="Your location" value="<?php echo trip_escape($formValues['origin']); ?>" required>
                        </div>

                        <div class="field field-full">
                            <label for="distance_km">Distance (km)</label>
                            <input type="number" id="distance_km" name="distance_km" min="1" step="0.1" value="<?php echo trip_escape($formValues['distance_km']); ?>" required>
                        </div>

                        <div class="field field-full">
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                            <label for="transport_mode">Transport Mode</label>
                            <select id="transport_mode" name="transport_mode" required>
                                <?php foreach ($transportModes as $key => $data): ?>
                                    <option value="<?php echo trip_escape($key); ?>" <?php echo $formValues['transport_mode'] === $key ? 'selected' : ''; ?>>
                                        <?php echo trip_escape($data['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
<<<<<<< HEAD
                            <p class="field-help field-help-balance">Change transport mode to recalculate your carbon footprint estimate.</p>
                        </div>

                        <div class="field">
                            <label for="sequence_order">Route Order</label>
                            <input type="number" id="sequence_order" name="sequence_order" min="1" step="1" value="<?php echo trip_escape($formValues['sequence_order']); ?>">
                            <p class="field-help field-help-balance"><?php echo trip_escape($routeOrderHelpText); ?></p>
                        </div>

                        <div class="field field-full field-trip-date">
                            <label for="trip_date">Trip Date</label>
                            <input type="date" id="trip_date" name="trip_date" value="<?php echo trip_escape($formValues['trip_date']); ?>" required>
                        </div>
                    </div>

                    <button type="submit">Update trip segment</button>
=======
                        </div>
                    </div>

                    <button type="submit">Update Trip</button>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                    <a href="destination_detail.php?id=<?php echo $destId; ?>" class="button button-secondary" style="display: block; text-align: center; margin-top: 12px;">Cancel</a>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
