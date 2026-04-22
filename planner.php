<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function planner_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function planner_clamp(float $value, float $minimum, float $maximum): float
{
    return max($minimum, min($maximum, $value));
}

function planner_format_mode(string $mode): string
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

function planner_get_destination_id_column(mysqli $conn): string
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

function planner_ensure_tables(mysqli $conn): void
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

planner_ensure_tables($conn);
$destinationIdColumn = planner_get_destination_id_column($conn);

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

$destinations = [];
$destinationsResult = $conn->query("SELECT * FROM destinations ORDER BY name ASC");

if ($destinationsResult) {
    while ($row = $destinationsResult->fetch_assoc()) {
        $destinations[] = $row;
    }
}

$formValues = [
    'trip_name' => '',
    'origin' => '',
    'destination_id' => '',
    'distance_km' => '',
    'transport_mode' => 'auto',
];

$plannerError = '';
$plannerSuccess = '';
$plannerResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
    $formValues['destination_id'] = trim($_POST['destination_id'] ?? '');
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'auto');

    $destinationId = (int) $formValues['destination_id'];
    $distanceKm = (float) $formValues['distance_km'];
    $transportMode = $formValues['transport_mode'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $destinationId <= 0 || $distanceKm <= 0) {
        $plannerError = 'Please complete the trip name, origin, destination, and distance fields.';
    } elseif (!array_key_exists($transportMode, $transportModes) && $transportMode !== 'auto') {
        $plannerError = 'Please choose a valid transport mode.';
    } else {
        $destinationStmt = $conn->prepare("SELECT * FROM destinations WHERE {$destinationIdColumn} = ? LIMIT 1");
        $destinationStmt->bind_param('i', $destinationId);
        $destinationStmt->execute();
        $destinationResult = $destinationStmt->get_result();
        $destination = $destinationResult ? $destinationResult->fetch_assoc() : null;
        $destinationStmt->close();

        if (!$destination) {
            $plannerError = 'The selected destination could not be found.';
        } else {
            $destinationRating = isset($destination['eco_rating']) ? (float) $destination['eco_rating'] : 3.0;
            $recommended = $recommendMode($distanceKm);
            $recommendedMode = $recommended['key'];
            $selectedMode = $transportMode === 'auto' ? $recommendedMode : $transportMode;
            $modeConfig = $transportModes[$selectedMode];
            $carbonEstimate = round($distanceKm * $modeConfig['factor'], 2);
            $destinationScore = $destinationRating * 10;
            $transportScore = planner_clamp(60 - ($carbonEstimate * 5), 0, 60);
            $ecoScore = (int) round(planner_clamp($destinationScore + $transportScore, 0, 100));
            $isEcoFriendly = $modeConfig['eco'] ? 1 : 0;

            $tripStmt = $conn->prepare('INSERT INTO trips (user_id, trip_name, origin, total_carbon_est) VALUES (?, ?, ?, ?)');
            $tripStmt->bind_param('issd', $_SESSION['user_id'], $formValues['trip_name'], $formValues['origin'], $carbonEstimate);

            if ($tripStmt->execute()) {
                $tripId = $conn->insert_id;
                $tripStmt->close();

                $itemStmt = $conn->prepare('INSERT INTO itinerary_items (trip_id, dest_id, transport_mode, is_eco_friendly, sequence_order, distance_km, carbon_est) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $sequenceOrder = 1;
                $itemStmt->bind_param('iisiidd', $tripId, $destinationId, $selectedMode, $isEcoFriendly, $sequenceOrder, $distanceKm, $carbonEstimate);
                $itemStmt->execute();
                $itemStmt->close();

                $plannerSuccess = 'Trip saved successfully. Your eco-score was calculated from the destination rating and transport choice.';
                $plannerResult = [
                    'trip_name' => $formValues['trip_name'],
                    'origin' => $formValues['origin'],
                    'destination_name' => $destination['name'] ?? 'Selected destination',
                    'destination_rating' => $destinationRating,
                    'distance_km' => $distanceKm,
                    'selected_mode' => $selectedMode,
                    'selected_mode_label' => planner_format_mode($selectedMode),
                    'selected_mode_tip' => $modeConfig['tip'],
                    'recommended_mode' => $recommendedMode,
                    'recommended_mode_label' => planner_format_mode($recommendedMode),
                    'recommended_reason' => $recommended['reason'],
                    'carbon_estimate' => $carbonEstimate,
                    'eco_score' => $ecoScore,
                    'is_eco_friendly' => $isEcoFriendly,
                    'destination_tips' => $destination['transport_tips'] ?? 'Choose public transport and local mobility options where available.',
                ];
            } else {
                $plannerError = 'Unable to save the trip right now. Please try again.';
                $tripStmt->close();
            }
        }
    }
}

$savedTrips = [];
$savedTripsStmt = $conn->prepare("SELECT t.trip_id, t.trip_name, t.origin, t.total_carbon_est, t.created_at, i.transport_mode, i.distance_km, i.is_eco_friendly, i.carbon_est, d.name AS destination_name, d.eco_rating FROM trips t LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id AND i.sequence_order = 1 LEFT JOIN destinations d ON d.{$destinationIdColumn} = i.dest_id WHERE t.user_id = ? ORDER BY t.created_at DESC, t.trip_id DESC");
$savedTripsStmt->bind_param('i', $_SESSION['user_id']);
$savedTripsStmt->execute();
$savedTripsResult = $savedTripsStmt->get_result();

if ($savedTripsResult) {
    while ($row = $savedTripsResult->fetch_assoc()) {
        $savedTrips[] = $row;
    }
}

$savedTripsStmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Trip Planner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
                    <h1>Eco Trip Planner</h1>
                    <p>Build a route, compare transport options, and keep carbon impact visible while you plan.</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Dashboard</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <div class="planner-layout">
                <section class="panel card">
                    <h2>Create a Trip</h2>
                    <p class="panel-lead">Use the planner to estimate travel emissions and get a recommendation for greener transport.</p>

                    <?php if ($plannerError !== ''): ?>
                        <p class="message error"><?php echo planner_escape($plannerError); ?></p>
                    <?php endif; ?>

                    <?php if ($plannerSuccess !== ''): ?>
                        <p class="message success"><?php echo planner_escape($plannerSuccess); ?></p>
                    <?php endif; ?>

                    <form method="POST" class="planner-form">
                        <div class="field-grid">
                            <div class="field">
                                <label for="trip_name">Trip name</label>
                                <input type="text" id="trip_name" name="trip_name" placeholder="Weekend rail escape" value="<?php echo planner_escape($formValues['trip_name']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="origin">Origin</label>
                                <input type="text" id="origin" name="origin" placeholder="Kuala Lumpur" value="<?php echo planner_escape($formValues['origin']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="destination_id">Destination</label>
                                <select id="destination_id" name="destination_id" required>
                                    <option value="">Select a destination</option>
                                    <?php foreach ($destinations as $destination): ?>
                                        <?php $destinationIdValue = $destination[$destinationIdColumn] ?? ''; ?>
                                        <option value="<?php echo planner_escape($destinationIdValue); ?>" <?php echo ((string) $destinationIdValue === $formValues['destination_id']) ? 'selected' : ''; ?>>
                                            <?php echo planner_escape($destination['name'] ?? 'Destination'); ?>
                                            <?php if (isset($destination['eco_rating'])): ?>
                                                - Eco rating <?php echo planner_escape($destination['eco_rating']); ?>/5
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="distance_km">Distance in km</label>
                                <input type="number" id="distance_km" name="distance_km" min="1" step="0.1" placeholder="120" value="<?php echo planner_escape($formValues['distance_km']); ?>" required>
                            </div>

                            <div class="field field-full">
                                <label for="transport_mode">Transport mode</label>
                                <select id="transport_mode" name="transport_mode">
                                    <option value="auto" <?php echo $formValues['transport_mode'] === 'auto' ? 'selected' : ''; ?>>Auto recommend the best option</option>
                                    <?php foreach ($transportModes as $modeKey => $modeData): ?>
                                        <option value="<?php echo planner_escape($modeKey); ?>" <?php echo $formValues['transport_mode'] === $modeKey ? 'selected' : ''; ?>>
                                            <?php echo planner_escape($modeData['label']); ?> - <?php echo planner_escape($modeData['badge']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit">Save trip and calculate eco-score</button>
                    </form>
                </section>

                <aside class="panel card">
                    <h2>Planner Guidance</h2>
                    <div class="summary-stack">
                        <div class="summary-item">
                            <span class="summary-label">Best default mode</span>
                            <strong>Train for longer routes</strong>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Short trip rule</span>
                            <strong>Bike or bus when possible</strong>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Flight rule</span>
                            <strong>Use only when rail or bus would be impractical</strong>
                        </div>
                    </div>

                    <?php if ($plannerResult !== null): ?>
                        <div class="result-box">
                            <p class="result-label">Trip preview</p>
                            <h3><?php echo planner_escape($plannerResult['trip_name']); ?></h3>
                            <p><strong><?php echo planner_escape($plannerResult['origin']); ?></strong> to <strong><?php echo planner_escape($plannerResult['destination_name']); ?></strong></p>
                            <div class="metric-grid">
                                <div class="metric">
                                    <span class="metric-label">Suggested mode</span>
                                    <strong class="metric-value"><?php echo planner_escape($plannerResult['recommended_mode_label']); ?></strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Carbon estimate</span>
                                    <strong class="metric-value"><?php echo planner_escape($plannerResult['carbon_estimate']); ?> kg CO2</strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Eco-score</span>
                                    <strong class="metric-value"><?php echo planner_escape($plannerResult['eco_score']); ?>/100</strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Eco-friendly</span>
                                    <strong class="metric-value"><?php echo $plannerResult['is_eco_friendly'] ? 'Yes' : 'No'; ?></strong>
                                </div>
                            </div>
                            <p class="planner-tip"><strong>Recommended tip:</strong> <?php echo planner_escape($plannerResult['recommended_reason']); ?></p>
                            <p class="planner-tip"><strong>Transport guidance:</strong> <?php echo planner_escape($plannerResult['selected_mode_tip']); ?></p>
                            <p class="planner-tip"><strong>Destination tip:</strong> <?php echo planner_escape($plannerResult['destination_tips']); ?></p>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <section class="panel card trip-history">
                <h2>Saved Trips</h2>
                <?php if (empty($savedTrips)): ?>
                    <p class="muted">No trips have been saved yet. Create your first eco-friendly route above.</p>
                <?php else: ?>
                    <div class="trip-list">
                        <?php foreach ($savedTrips as $trip): ?>
                            <article class="trip-entry">
                                <div class="trip-head">
                                    <div>
                                        <h3><?php echo planner_escape($trip['trip_name']); ?></h3>
                                        <p class="muted"><?php echo planner_escape($trip['origin']); ?> to <?php echo planner_escape($trip['destination_name'] ?? 'Destination'); ?></p>
                                    </div>
                                    <span class="status-pill <?php echo !empty($trip['is_eco_friendly']) ? 'status-eco' : 'status-neutral'; ?>">
                                        <?php echo !empty($trip['is_eco_friendly']) ? 'Eco-friendly' : 'Higher impact'; ?>
                                    </span>
                                </div>
                                <div class="trip-meta">
                                    <span><?php echo planner_escape(planner_format_mode((string) $trip['transport_mode'])); ?></span>
                                    <span><?php echo planner_escape($trip['distance_km']); ?> km</span>
                                    <span><?php echo planner_escape($trip['total_carbon_est']); ?> kg CO2</span>
                                    <?php if (isset($trip['eco_rating'])): ?>
                                        <span>Destination eco-rating <?php echo planner_escape($trip['eco_rating']); ?>/5</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
</html>