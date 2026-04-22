<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$genericQuickTips = [
    'Plan ahead to find better transit options.',
    'Consider traveling during off-peak hours to reduce congestion.',
    'Combine multiple short trips into one longer journey.',
    'Choose accommodations within walking distance of attractions.',
    'Use public transportation apps to plan efficient routes.',
    'Pack light to reduce fuel consumption on transportation.',
    'Stay longer in fewer destinations to reduce travel frequency.',
    'Research local car-sharing or bike-sharing programs.',
    'Offset unavoidable flights through carbon credit programs.',
    'Choose direct routes to minimize time and emissions.',
];

function detail_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function detail_clamp(float $value, float $minimum, float $maximum): float
{
    return max($minimum, min($maximum, $value));
}

function detail_format_mode(string $mode): string
{
    $labels = [
        'bike' => 'Bike',
        'bus' => 'Bus',
        'train' => 'Train',
        'electric_vehicle' => 'Electric Vehicle',
        'flight' => 'Flight',
    ];

    return $labels[$mode] ?? ucfirst(str_replace('_', ' ', $mode));
}

function detail_get_destination_id_column(mysqli $conn): string
{
    $column = 'id';
    $columns = $conn->query('SHOW COLUMNS FROM destinations');

    if ($columns) {
        while ($row = $columns->fetch_assoc()) {
            if ($row['Field'] === 'dest_id') {
                return 'dest_id';
            }

            if ($row['Field'] === 'id') {
                $column = 'id';
            }
        }
    }

    return $column;
}

function detail_ensure_tables(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS trips (
            trip_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            trip_name VARCHAR(150) NOT NULL,
            origin VARCHAR(150) NOT NULL,
            total_carbon_est DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS itinerary_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            trip_id INT NOT NULL,
            dest_id INT NOT NULL,
            transport_mode VARCHAR(30) NOT NULL,
            is_eco_friendly TINYINT(1) NOT NULL DEFAULT 0,
            sequence_order INT NOT NULL DEFAULT 1,
            distance_km DECIMAL(10,2) NOT NULL DEFAULT 0,
            carbon_est DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (trip_id),
            INDEX (dest_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

$transportModes = [
    'bike' => [
        'label' => 'Bike',
        'factor' => 0.00,
        'eco' => true,
        'badge' => 'Best for short city trips',
        'tip' => 'Choose bike routes, shared bike docks, and low-traffic streets.',
    ],
    'bus' => [
        'label' => 'Bus',
        'factor' => 0.08,
        'eco' => true,
        'badge' => 'Efficient for regional travel',
        'tip' => 'Use direct lines and public transit hubs to reduce transfers.',
    ],
    'train' => [
        'label' => 'Train',
        'factor' => 0.04,
        'eco' => true,
        'badge' => 'Usually the lowest-carbon long-distance option',
        'tip' => 'Pick rail over short-haul flights whenever a rail corridor exists.',
    ],
    'electric_vehicle' => [
        'label' => 'Electric Vehicle',
        'factor' => 0.12,
        'eco' => true,
        'badge' => 'Good for flexible ground travel',
        'tip' => 'Combine with charging stops and avoid unnecessary detours.',
    ],
    'flight' => [
        'label' => 'Flight',
        'factor' => 0.25,
        'eco' => false,
        'badge' => 'Highest emissions; use only if necessary',
        'tip' => 'If the route is under roughly 800 km, consider rail or coach first.',
    ],
];

detail_ensure_tables($conn);
$destinationIdColumn = detail_get_destination_id_column($conn);

$recommendMode = function (float $distanceKm) use ($transportModes): array {
    if ($distanceKm <= 15) {
        return ['key' => 'bike', 'reason' => 'Short distances are best handled by bike or walking-friendly routes.'];
    }

    if ($distanceKm <= 120) {
        return ['key' => 'bus', 'reason' => 'For short regional travel, bus travel keeps emissions low and stays flexible.'];
    }

    if ($distanceKm <= 1200) {
        return ['key' => 'train', 'reason' => 'For this distance, train travel usually produces the lowest carbon footprint.'];
    }

    return ['key' => 'train', 'reason' => 'Long-distance rail is still preferred whenever it is available on the route.'];
};

$destId = (int) ($_GET['id'] ?? 0);

if ($destId <= 0) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_destination') {
    $deleteConfirm = $_POST['confirm_delete'] ?? '';
    if ($deleteConfirm === 'yes') {
        $getTripsStmt = $conn->prepare("SELECT trip_id FROM itinerary_items WHERE dest_id = ?");
        $getTripsStmt->bind_param('i', $destId);
        $getTripsStmt->execute();
        $tripsResult = $getTripsStmt->get_result();

        while ($tripRow = $tripsResult->fetch_assoc()) {
            $tripId = $tripRow['trip_id'];
            $deleteItemsStmt = $conn->prepare("DELETE FROM itinerary_items WHERE trip_id = ?");
            $deleteItemsStmt->bind_param('i', $tripId);
            $deleteItemsStmt->execute();
            $deleteItemsStmt->close();
            $deleteTripStmt = $conn->prepare("DELETE FROM trips WHERE trip_id = ?");
            $deleteTripStmt->bind_param('i', $tripId);
            $deleteTripStmt->execute();
            $deleteTripStmt->close();
        }
        $getTripsStmt->close();

        $deleteDestStmt = $conn->prepare("DELETE FROM destinations WHERE {$destinationIdColumn} = ?");
        $deleteDestStmt->bind_param('i', $destId);
        $deleteDestStmt->execute();
        $deleteDestStmt->close();

        header("Location: index.php?deleted=1");
        exit();
    }
}

$destStmt = $conn->prepare("SELECT * FROM destinations WHERE {$destinationIdColumn} = ? LIMIT 1");
$destStmt->bind_param('i', $destId);
$destStmt->execute();
$destResult = $destStmt->get_result();
$destination = $destResult ? $destResult->fetch_assoc() : null;
$destStmt->close();

if (!$destination) {
    header("Location: index.php");
    exit();
}

$formValues = [
    'trip_name' => '',
    'origin' => '',
    'distance_km' => '',
    'transport_mode' => 'auto',
];

$detailError = '';
$detailSuccess = '';
$detailResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'auto');

    $distanceKm = (float) $formValues['distance_km'];
    $transportMode = $formValues['transport_mode'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $distanceKm <= 0) {
        $detailError = 'Please complete the trip name, origin, and distance fields.';
    } elseif (!array_key_exists($transportMode, $transportModes) && $transportMode !== 'auto') {
        $detailError = 'Please choose a valid transport mode.';
    } else {
        $destinationRating = isset($destination['eco_rating']) ? (float) $destination['eco_rating'] : 3.0;
        $recommended = $recommendMode($distanceKm);
        $recommendedMode = $recommended['key'];
        $selectedMode = $transportMode === 'auto' ? $recommendedMode : $transportMode;
        $modeConfig = $transportModes[$selectedMode];
        $carbonEstimate = round($distanceKm * $modeConfig['factor'], 2);
        $destinationScore = $destinationRating * 10;
        $transportScore = detail_clamp(60 - ($carbonEstimate * 5), 0, 60);
        $ecoScore = (int) round(detail_clamp($destinationScore + $transportScore, 0, 100));
        $isEcoFriendly = $modeConfig['eco'] ? 1 : 0;

        $tripStmt = $conn->prepare('INSERT INTO trips (user_id, trip_name, origin, total_carbon_est) VALUES (?, ?, ?, ?)');
        $tripStmt->bind_param('issd', $_SESSION['user_id'], $formValues['trip_name'], $formValues['origin'], $carbonEstimate);

        if ($tripStmt->execute()) {
            $tripId = $conn->insert_id;
            $tripStmt->close();

            $itemStmt = $conn->prepare('INSERT INTO itinerary_items (trip_id, dest_id, transport_mode, is_eco_friendly, sequence_order, distance_km, carbon_est) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $sequenceOrder = 1;
            $itemStmt->bind_param('iisiidd', $tripId, $destId, $selectedMode, $isEcoFriendly, $sequenceOrder, $distanceKm, $carbonEstimate);
            $itemStmt->execute();
            $itemStmt->close();

            $detailSuccess = 'Trip saved successfully!';
            $detailResult = [
                'trip_name' => $formValues['trip_name'],
                'origin' => $formValues['origin'],
                'destination_name' => $destination['name'] ?? 'Selected destination',
                'destination_rating' => $destinationRating,
                'distance_km' => $distanceKm,
                'selected_mode' => $selectedMode,
                'selected_mode_label' => detail_format_mode($selectedMode),
                'selected_mode_tip' => $modeConfig['tip'],
                'recommended_mode' => $recommendedMode,
                'recommended_mode_label' => detail_format_mode($recommendedMode),
                'recommended_reason' => $recommended['reason'],
                'carbon_estimate' => $carbonEstimate,
                'eco_score' => $ecoScore,
                'is_eco_friendly' => $isEcoFriendly,
                'destination_tips' => $destination['transport_tips'] ?? 'Choose public transport and local mobility options where available.',
            ];
        } else {
            $detailError = 'Unable to save the trip right now. Please try again.';
            $tripStmt->close();
        }
    }
}

$tripsToDestination = [];
$tripsStmt = $conn->prepare("SELECT t.trip_id, t.trip_name, t.origin, t.total_carbon_est, t.created_at, i.transport_mode, i.distance_km, i.is_eco_friendly, i.carbon_est FROM trips t LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id AND i.sequence_order = 1 WHERE i.dest_id = ? ORDER BY t.created_at DESC");
$tripsStmt->bind_param('i', $destId);
$tripsStmt->execute();
$tripsResult = $tripsStmt->get_result();

if ($tripsResult) {
    while ($row = $tripsResult->fetch_assoc()) {
        $tripsToDestination[] = $row;
    }
}

$tripsStmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo detail_escape($destination['name']); ?> - Destination Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
                    <h1><?php echo detail_escape($destination['name']); ?></h1>
                    <p>Plan an eco-friendly trip to this destination</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Back to Dashboard</a>
                    <a class="button button-secondary" href="destination_form.php?id=<?php echo $destId; ?>">Edit Destination</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this destination and all associated trips? This cannot be undone.');"><input type="hidden" name="action" value="delete_destination"><input type="hidden" name="confirm_delete" value="yes"><button type="submit" class="button btn-delete-dest">Delete Destination</button></form>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="destination-hero card">
                <div class="hero-content">
                    <div class="hero-main">
                        <h2>Destination Overview</h2>
                        <p class="rating-badge">
                            <span class="rating">Eco-Rating: <?php echo detail_escape($destination['eco_rating']); ?> / 5</span>
                        </p>
                        <p class="eco-tips"><strong>Sustainable Transport Tip:</strong><br><?php echo detail_escape($destination['transport_tips']); ?></p>
                    </div>
                </div>
            </section>

            <div class="detail-layout">
                <section class="panel card">
                    <h2>Create a Trip to <?php echo detail_escape($destination['name']); ?></h2>
                    <p class="panel-lead">Plan your route and see carbon estimates based on your transport choice.</p>

                    <?php if ($detailError !== ''): ?>
                        <p class="message error"><?php echo detail_escape($detailError); ?></p>
                    <?php endif; ?>

                    <?php if ($detailSuccess !== ''): ?>
                        <p class="message success"><?php echo detail_escape($detailSuccess); ?></p>
                    <?php endif; ?>

                    <form method="POST" class="planner-form">
                        <div class="field-grid">
                            <div class="field">
                                <label for="trip_name">Trip name</label>
                                <input type="text" id="trip_name" name="trip_name" placeholder="My eco trip" value="<?php echo detail_escape($formValues['trip_name']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="origin">Origin</label>
                                <input type="text" id="origin" name="origin" placeholder="Your location" value="<?php echo detail_escape($formValues['origin']); ?>" required>
                            </div>

                            <div class="field field-full">
                                <label for="distance_km">Distance in km</label>
                                <input type="number" id="distance_km" name="distance_km" min="1" step="0.1" placeholder="100" value="<?php echo detail_escape($formValues['distance_km']); ?>" required>
                            </div>

                            <div class="field field-full">
                                <label for="transport_mode">Transport mode</label>
                                <select id="transport_mode" name="transport_mode">
                                    <option value="auto" <?php echo $formValues['transport_mode'] === 'auto' ? 'selected' : ''; ?>>Auto recommend the best option</option>
                                    <?php foreach ($transportModes as $modeKey => $modeData): ?>
                                        <option value="<?php echo detail_escape($modeKey); ?>" <?php echo $formValues['transport_mode'] === $modeKey ? 'selected' : ''; ?>>
                                            <?php echo detail_escape($modeData['label']); ?> - <?php echo detail_escape($modeData['badge']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit">Create trip and calculate eco-score</button>
                    </form>
                </section>

                <aside class="panel card">
                    <h2>Trip Summary</h2>
                    <?php if ($detailResult !== null): ?>
                        <div class="result-box">
                            <p class="result-label">Latest trip preview</p>
                            <h3><?php echo detail_escape($detailResult['trip_name']); ?></h3>
                            <p><strong><?php echo detail_escape($detailResult['origin']); ?></strong> to <strong><?php echo detail_escape($detailResult['destination_name']); ?></strong></p>
                            <div class="metric-grid">
                                <div class="metric">
                                    <span class="metric-label">Suggested mode</span>
                                    <strong class="metric-value"><?php echo detail_escape($detailResult['recommended_mode_label']); ?></strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Carbon estimate</span>
                                    <strong class="metric-value"><?php echo detail_escape($detailResult['carbon_estimate']); ?> kg CO2</strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Eco-score</span>
                                    <strong class="metric-value"><?php echo detail_escape($detailResult['eco_score']); ?>/100</strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Eco-friendly</span>
                                    <strong class="metric-value"><?php echo $detailResult['is_eco_friendly'] ? 'Yes ✓' : 'No'; ?></strong>
                                </div>
                            </div>
                            <p class="planner-tip"><strong>Recommendation:</strong> <?php echo detail_escape($detailResult['recommended_reason']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="summary-stack">
                            <div class="summary-item">
                                <span class="summary-label">Eco-Rating</span>
                                <strong><?php echo detail_escape($destination['eco_rating']); ?>/5</strong>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Quick tip</span>
                                <strong>Choose sustainable transport options</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <section class="panel card trip-history">
                <h2>Trips to <?php echo detail_escape($destination['name']); ?></h2>
                <?php if (empty($tripsToDestination)): ?>
                    <p class="muted">No trips to this destination yet. Create your first trip above.</p>
                <?php else: ?>
                    <table class="trip-table">
                        <thead>
                            <tr>
                                <th>Trip Name</th>
                                <th>From</th>
                                <th>Distance</th>
                                <th>Transport</th>
                                <th>Carbon (kg CO2)</th>
                                <th>Eco-Friendly</th>
                                <th>Date</th>
                                <th colspan="2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tripsToDestination as $trip): ?>
                                <tr>
                                    <td><?php echo detail_escape($trip['trip_name']); ?></td>
                                    <td><?php echo detail_escape($trip['origin']); ?></td>
                                    <td><?php echo detail_escape($trip['distance_km']); ?> km</td>
                                    <td><?php echo detail_escape(detail_format_mode((string) $trip['transport_mode'])); ?></td>
                                    <td><?php echo detail_escape($trip['total_carbon_est']); ?></td>
                                    <td><span class="status-pill <?php echo !empty($trip['is_eco_friendly']) ? 'status-eco' : 'status-neutral'; ?>"><?php echo !empty($trip['is_eco_friendly']) ? '✓ Yes' : 'No'; ?></span></td>
                                    <td><?php echo detail_escape(date('M d, Y', strtotime($trip['created_at']))); ?></td>
                                    <td>
                                        <a href="trip_edit.php?id=<?php echo $trip['trip_id']; ?>" class="btn-action btn-edit">Edit</a>
                                    </td>
                                    <td>
                                        <a href="trip_handler.php?action=delete&trip_id=<?php echo $trip['trip_id']; ?>&dest_id=<?php echo $destId; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this trip?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="panel card quick-tips-section">
                <h2>Quick Sustainability Tips</h2>
                <p class="panel-lead">Helpful guidance for eco-friendly travel planning:</p>
                <div class="tips-grid">
                    <?php $tips = array_slice($genericQuickTips, 0, 6);
                    foreach ($tips as $index => $tip): ?>
                        <div class="tip-item">
                            <span class="tip-number"><?php echo $index + 1; ?></span>
                            <p><?php echo detail_escape($tip); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
