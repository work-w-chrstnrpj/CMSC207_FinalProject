<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function trip_escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$tripId = (int) ($_GET['id'] ?? 0);

if ($tripId <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT t.*, i.dest_id, i.distance_km, i.transport_mode FROM trips t LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id AND i.sequence_order = 1 WHERE t.trip_id = ? LIMIT 1");
$stmt->bind_param('i', $tripId);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();
$stmt->close();

if (!$trip) {
    header("Location: index.php");
    exit();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Trip</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container" style="max-width: 600px;">
            <header class="topbar">
                <div>
                    <h1>Edit Trip</h1>
                    <p><?php echo trip_escape($trip['trip_name']); ?></p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="destination_detail.php?id=<?php echo $destId; ?>">Back</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="panel card">
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
                            <label for="transport_mode">Transport Mode</label>
                            <select id="transport_mode" name="transport_mode" required>
                                <?php foreach ($transportModes as $key => $data): ?>
                                    <option value="<?php echo trip_escape($key); ?>" <?php echo $formValues['transport_mode'] === $key ? 'selected' : ''; ?>>
                                        <?php echo trip_escape($data['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit">Update Trip</button>
                    <a href="destination_detail.php?id=<?php echo $destId; ?>" class="button button-secondary" style="display: block; text-align: center; margin-top: 12px;">Cancel</a>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
