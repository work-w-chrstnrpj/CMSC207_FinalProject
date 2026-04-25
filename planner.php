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

function planner_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

<<<<<<< HEAD
travel_ensure_trip_tables($conn);

$destinationIdColumn = travel_get_destination_id_column($conn);
$transportModes = travel_get_transport_modes();
$stageOptions = travel_get_stage_options();
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

$destinations = [];
$destinationsResult = $conn->query("SELECT * FROM destinations ORDER BY name ASC");

if ($destinationsResult) {
    while ($row = $destinationsResult->fetch_assoc()) {
<<<<<<< HEAD
        $did = $row['id'] ?? $row['dest_id'] ?? 0;
        if ($did) {
            $row['eco_rating'] = travel_calculate_eco_rating($conn, $did);
        }
        $destinations[] = $row;
    }

    $destinationsResult->close();
=======
        $destinations[] = $row;
    }
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
}

$formValues = [
    'trip_name' => '',
    'origin' => '',
    'destination_id' => '',
<<<<<<< HEAD
    'trip_date' => travel_get_default_trip_date(),
    'distance_km' => '',
    'transport_mode' => 'auto',
    'trip_stage' => 'to_destination',
    'sequence_order' => '',
=======
    'distance_km' => '',
    'transport_mode' => 'auto',
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
];

$plannerError = '';
$plannerSuccess = '';
$plannerResult = null;
<<<<<<< HEAD
$routeOrderHelpText = 'Choose a destination goal and trip date to preview the next route order.';
=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
    $formValues['destination_id'] = trim($_POST['destination_id'] ?? '');
<<<<<<< HEAD
    $formValues['trip_date'] = trim($_POST['trip_date'] ?? travel_get_default_trip_date());
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'auto');
    $formValues['trip_stage'] = trim($_POST['trip_stage'] ?? 'to_destination');
    $formValues['sequence_order'] = trim($_POST['sequence_order'] ?? '');

    $destinationId = (int) $formValues['destination_id'];
    $tripDate = $formValues['trip_date'];
    $distanceKm = (float) $formValues['distance_km'];
    $transportMode = $formValues['transport_mode'];
    $tripStage = $formValues['trip_stage'];
    $sequenceOrder = $formValues['sequence_order'] === '' ? 0 : (int) $formValues['sequence_order'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $destinationId <= 0 || $distanceKm <= 0 || $tripDate === '') {
        $plannerError = 'Please complete the from, to, destination goal, trip date, and distance fields.';
    } elseif (!travel_is_valid_trip_date($tripDate)) {
        $plannerError = 'Please choose a valid trip date.';
    } elseif (!array_key_exists($transportMode, $transportModes) && $transportMode !== 'auto') {
        $plannerError = 'Please choose a valid transport mode.';
    } elseif (!array_key_exists($tripStage, $stageOptions)) {
        $plannerError = 'Please choose a valid trip stage.';
    } elseif ($formValues['sequence_order'] !== '' && $sequenceOrder <= 0) {
        $plannerError = 'Route order must be 1 or higher when you set it manually.';
=======
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'auto');

    $destinationId = (int) $formValues['destination_id'];
    $distanceKm = (float) $formValues['distance_km'];
    $transportMode = $formValues['transport_mode'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $destinationId <= 0 || $distanceKm <= 0) {
        $plannerError = 'Please complete the trip name, origin, destination, and distance fields.';
    } elseif (!array_key_exists($transportMode, $transportModes) && $transportMode !== 'auto') {
        $plannerError = 'Please choose a valid transport mode.';
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
    } else {
        $destinationStmt = $conn->prepare("SELECT * FROM destinations WHERE {$destinationIdColumn} = ? LIMIT 1");
        $destinationStmt->bind_param('i', $destinationId);
        $destinationStmt->execute();
        $destinationResult = $destinationStmt->get_result();
        $destination = $destinationResult ? $destinationResult->fetch_assoc() : null;
        $destinationStmt->close();

<<<<<<< HEAD
        if ($destination) {
            $destination['eco_rating'] = travel_calculate_eco_rating($conn, $destinationId);
        }

        if (!$destination) {
            $plannerError = 'The selected destination goal could not be found.';
        } else {
            if ($sequenceOrder <= 0) {
                $sequenceOrder = travel_get_next_sequence_order($conn, $destinationId, $tripStage, null, $tripDate);
            }

            $calculation = travel_calculate_segment_result($destination, $distanceKm, $transportMode);
            $tripStmt = $conn->prepare('INSERT INTO trips (user_id, trip_name, origin, trip_date, total_carbon_est) VALUES (?, ?, ?, ?, ?)');
            $tripStmt->bind_param('isssd', $_SESSION['user_id'], $formValues['trip_name'], $formValues['origin'], $tripDate, $calculation['carbon_estimate']);
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

            if ($tripStmt->execute()) {
                $tripId = $conn->insert_id;
                $tripStmt->close();

<<<<<<< HEAD
                $itemStmt = $conn->prepare('INSERT INTO itinerary_items (trip_id, dest_id, transport_mode, is_eco_friendly, sequence_order, distance_km, carbon_est, trip_stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $itemStmt->bind_param(
                    'iisiidds',
                    $tripId,
                    $destinationId,
                    $calculation['selected_mode'],
                    $calculation['is_eco_friendly'],
                    $sequenceOrder,
                    $distanceKm,
                    $calculation['carbon_estimate'],
                    $tripStage
                );
                $itemStmt->execute();
                $itemStmt->close();

                $plannerSuccess = 'Trip segment saved successfully. This destination goal can now hold multiple commute fares and local rides.';
=======
                $itemStmt = $conn->prepare('INSERT INTO itinerary_items (trip_id, dest_id, transport_mode, is_eco_friendly, sequence_order, distance_km, carbon_est) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $sequenceOrder = 1;
                $itemStmt->bind_param('iisiidd', $tripId, $destinationId, $selectedMode, $isEcoFriendly, $sequenceOrder, $distanceKm, $carbonEstimate);
                $itemStmt->execute();
                $itemStmt->close();

                $plannerSuccess = 'Trip saved successfully. Your eco-score was calculated from the destination rating and transport choice.';
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                $plannerResult = [
                    'trip_name' => $formValues['trip_name'],
                    'origin' => $formValues['origin'],
                    'destination_name' => $destination['name'] ?? 'Selected destination',
<<<<<<< HEAD
                    'trip_date' => $tripDate,
                    'distance_km' => $distanceKm,
                    'selected_mode' => $calculation['selected_mode'],
                    'selected_mode_label' => travel_format_mode($calculation['selected_mode']),
                    'selected_mode_tip' => $calculation['mode_config']['tip'],
                    'recommended_mode_label' => travel_format_mode($calculation['recommended_mode']),
                    'recommended_reason' => $calculation['recommended']['reason'],
                    'carbon_estimate' => $calculation['carbon_estimate'],
                    'eco_score' => $calculation['eco_score'],
                    'is_eco_friendly' => $calculation['is_eco_friendly'],
                    'destination_tips' => $destination['transport_tips'] ?? 'Choose public transport and local mobility options where available.',
                    'trip_stage' => $tripStage,
                    'trip_stage_label' => travel_format_stage($tripStage),
                    'trip_stage_class' => travel_stage_badge_class($tripStage),
                    'sequence_order' => $sequenceOrder,
                ];

                $formValues = [
                    'trip_name' => '',
                    'origin' => '',
                    'destination_id' => '',
                    'trip_date' => travel_get_default_trip_date(),
                    'distance_km' => '',
                    'transport_mode' => 'auto',
                    'trip_stage' => 'to_destination',
                    'sequence_order' => '',
                ];
            } else {
                $plannerError = 'Unable to save the trip segment right now. Please try again.';
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                $tripStmt->close();
            }
        }
    }
}

<<<<<<< HEAD
if ((int) $formValues['destination_id'] > 0 && travel_is_valid_trip_date((string) $formValues['trip_date'])) {
    $routeOrderHelpText = travel_build_next_order_help(
        $conn,
        (int) $formValues['destination_id'],
        (string) $formValues['trip_date']
    );
}

$savedTrips = [];
$savedTripsStmt = $conn->prepare(
    "SELECT
        t.trip_id,
        t.trip_name,
        t.origin,
        COALESCE(t.trip_date, DATE(t.created_at)) AS trip_date_value,
        t.total_carbon_est,
        t.created_at,
        i.transport_mode,
        i.distance_km,
        i.is_eco_friendly,
        i.trip_stage,
        i.sequence_order,
        d.name AS destination_name,
        d.eco_rating,
        d.{$destinationIdColumn} AS destination_id
    FROM trips t
    LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id
    LEFT JOIN destinations d ON d.{$destinationIdColumn} = i.dest_id
    WHERE t.user_id = ?
    ORDER BY COALESCE(t.trip_date, DATE(t.created_at)) DESC, t.created_at DESC, t.trip_id DESC"
);
=======
$savedTrips = [];
$savedTripsStmt = $conn->prepare("SELECT t.trip_id, t.trip_name, t.origin, t.total_carbon_est, t.created_at, i.transport_mode, i.distance_km, i.is_eco_friendly, i.carbon_est, d.name AS destination_name, d.eco_rating FROM trips t LEFT JOIN itinerary_items i ON i.trip_id = t.trip_id AND i.sequence_order = 1 LEFT JOIN destinations d ON d.{$destinationIdColumn} = i.dest_id WHERE t.user_id = ? ORDER BY t.created_at DESC, t.trip_id DESC");
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
$savedTripsStmt->bind_param('i', $_SESSION['user_id']);
$savedTripsStmt->execute();
$savedTripsResult = $savedTripsStmt->get_result();

if ($savedTripsResult) {
    while ($row = $savedTripsResult->fetch_assoc()) {
        $savedTrips[] = $row;
    }
}

$savedTripsStmt->close();
<<<<<<< HEAD

$destRatingsCache = [];
foreach ($savedTrips as &$trip) {
    $did = $trip['destination_id'];
    if (!empty($did)) {
        if (!isset($destRatingsCache[$did])) {
            $destRatingsCache[$did] = travel_calculate_eco_rating($conn, $did);
        }
        $trip['eco_rating'] = $destRatingsCache[$did];
    }
}
unset($trip);
=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
?>
<!DOCTYPE html>
<html>
<head>
<<<<<<< HEAD
    <title>Trip Segments</title>
=======
    <title>Trip Planner</title>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
<<<<<<< HEAD
                    <h1>Destination Trip Segments</h1>
                    <p>Add one commute fare or route segment at a time under a destination goal.</p>
=======
                    <h1>Eco Trip Planner</h1>
                    <p>Build a route, compare transport options, and keep carbon impact visible while you plan.</p>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Dashboard</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <div class="planner-layout">
                <section class="panel card">
<<<<<<< HEAD
                    <h2>Add a Trip Segment</h2>
                    <p class="panel-lead">Each trip here should be one fare or one route leg under a destination goal.</p>
                    <p class="helper-note">Choose the destination goal first, then save each segment that belongs to that goal.</p>
=======
                    <h2>Create a Trip</h2>
                    <p class="panel-lead">Use the planner to estimate travel emissions and get a recommendation for greener transport.</p>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

                    <?php if ($plannerError !== ''): ?>
                        <p class="message error"><?php echo planner_escape($plannerError); ?></p>
                    <?php endif; ?>

                    <?php if ($plannerSuccess !== ''): ?>
                        <p class="message success"><?php echo planner_escape($plannerSuccess); ?></p>
                    <?php endif; ?>

<<<<<<< HEAD
                    <form method="POST" class="planner-form trip-segment-form">
                        <div class="field-grid trip-segment-grid">
                            <div class="field field-full">
                                <label for="destination_id">Destination Goal</label>
                                <select id="destination_id" name="destination_id" required>
                                    <option value="">Select a destination goal</option>
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
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
<<<<<<< HEAD
                                <label for="origin">From</label>
                                <input type="text" id="origin" name="origin" placeholder="Starting point" value="<?php echo planner_escape($formValues['origin']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="trip_name">To</label>
                                <input type="text" id="trip_name" name="trip_name" placeholder="Arrival point" value="<?php echo planner_escape($formValues['trip_name']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="trip_stage">Trip Stage</label>
                                <select id="trip_stage" name="trip_stage" required>
                                    <?php foreach ($stageOptions as $stageKey => $stageData): ?>
                                        <option value="<?php echo planner_escape($stageKey); ?>" <?php echo $formValues['trip_stage'] === $stageKey ? 'selected' : ''; ?>>
                                            <?php echo planner_escape($stageData['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="distance_km">Distance in km</label>
                                <input type="number" id="distance_km" name="distance_km" min="0.1" step="0.1" placeholder="120" value="<?php echo planner_escape($formValues['distance_km']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="transport_mode">Transport Mode</label>
=======
                                <label for="distance_km">Distance in km</label>
                                <input type="number" id="distance_km" name="distance_km" min="1" step="0.1" placeholder="120" value="<?php echo planner_escape($formValues['distance_km']); ?>" required>
                            </div>

                            <div class="field field-full">
                                <label for="transport_mode">Transport mode</label>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                                <select id="transport_mode" name="transport_mode">
                                    <option value="auto" <?php echo $formValues['transport_mode'] === 'auto' ? 'selected' : ''; ?>>Auto recommend the best option</option>
                                    <?php foreach ($transportModes as $modeKey => $modeData): ?>
                                        <option value="<?php echo planner_escape($modeKey); ?>" <?php echo $formValues['transport_mode'] === $modeKey ? 'selected' : ''; ?>>
                                            <?php echo planner_escape($modeData['label']); ?> - <?php echo planner_escape($modeData['badge']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
<<<<<<< HEAD
                                <p class="field-help field-help-balance">Leave as Auto to let the system recommend the most eco-friendly option based on distance.</p>
                            </div>

                            <div class="field">
                                <label for="sequence_order">Route Order</label>
                                <input type="number" id="sequence_order" name="sequence_order" min="1" step="1" placeholder="Auto" value="<?php echo planner_escape($formValues['sequence_order']); ?>">
                                <p class="field-help field-help-balance"><?php echo planner_escape($routeOrderHelpText); ?></p>
                            </div>

                            <div class="field field-full field-trip-date">
                                <label for="trip_date">Trip Date</label>
                                <input type="date" id="trip_date" name="trip_date" value="<?php echo planner_escape($formValues['trip_date']); ?>" required>
                            </div>
                        </div>

                        <button type="submit">Save trip segment</button>
=======
                            </div>
                        </div>

                        <button type="submit">Save trip and calculate eco-score</button>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                    </form>
                </section>

                <aside class="panel card">
<<<<<<< HEAD
                    <h2>How It Works</h2>
                    <div class="summary-stack">
                        <div class="summary-item">
                            <span class="summary-label">Destination</span>
                            <strong>One travel goal that groups related trip segments</strong>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Trip</span>
                            <strong>One fare or one route leg only</strong>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Route order</span>
                            <strong>Save each segment in the order you plan to take it</strong>
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                        </div>
                    </div>

                    <?php if ($plannerResult !== null): ?>
                        <div class="result-box">
<<<<<<< HEAD
                            <p class="result-label">Latest segment preview</p>
                            <h3><?php echo planner_escape($plannerResult['destination_name']); ?></h3>
                            <p class="trip-route"><strong><?php echo planner_escape($plannerResult['origin']); ?></strong> to <strong><?php echo planner_escape($plannerResult['trip_name']); ?></strong></p>
                            <p><span class="stage-pill <?php echo planner_escape($plannerResult['trip_stage_class']); ?>"><?php echo planner_escape($plannerResult['trip_stage_label']); ?></span></p>
                            <div class="metric-grid">
                                <div class="metric">
                                    <span class="metric-label">Trip date</span>
                                    <strong class="metric-value"><?php echo planner_escape(travel_format_trip_date($plannerResult['trip_date'])); ?></strong>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Route order</span>
                                    <strong class="metric-value"><?php echo planner_escape($plannerResult['sequence_order']); ?></strong>
                                </div>
                                <div class="metric">
=======
                            <p class="result-label">Trip preview</p>
                            <h3><?php echo planner_escape($plannerResult['trip_name']); ?></h3>
                            <p><strong><?php echo planner_escape($plannerResult['origin']); ?></strong> to <strong><?php echo planner_escape($plannerResult['destination_name']); ?></strong></p>
                            <div class="metric-grid">
                                <div class="metric">
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
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
<<<<<<< HEAD
                            </div>
                            <p class="planner-tip"><strong>Recommendation:</strong> <?php echo planner_escape($plannerResult['recommended_reason']); ?></p>
                            <p class="planner-tip"><strong>Transport guidance:</strong> <?php echo planner_escape($plannerResult['selected_mode_tip']); ?></p>
                            <p class="planner-tip"><strong>Destination note:</strong> <?php echo planner_escape($plannerResult['destination_tips']); ?></p>
=======
                                <div class="metric">
                                    <span class="metric-label">Eco-friendly</span>
                                    <strong class="metric-value"><?php echo $plannerResult['is_eco_friendly'] ? 'Yes' : 'No'; ?></strong>
                                </div>
                            </div>
                            <p class="planner-tip"><strong>Recommended tip:</strong> <?php echo planner_escape($plannerResult['recommended_reason']); ?></p>
                            <p class="planner-tip"><strong>Transport guidance:</strong> <?php echo planner_escape($plannerResult['selected_mode_tip']); ?></p>
                            <p class="planner-tip"><strong>Destination tip:</strong> <?php echo planner_escape($plannerResult['destination_tips']); ?></p>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <section class="panel card trip-history">
<<<<<<< HEAD
                <h2>Recent Trip Segments</h2>
                <?php if (empty($savedTrips)): ?>
                    <p class="muted">No trip segments saved yet. Add your first route leg above.</p>
                <?php else: ?>
                    <div class="trip-list">
                        <?php foreach ($savedTrips as $trip): ?>
                            <?php $tripStage = (string) ($trip['trip_stage'] ?? 'to_destination'); ?>
                            <article class="trip-entry">
                                <div class="trip-head">
                                    <div>
                                        <h3><?php echo planner_escape($trip['destination_name'] ?? 'Destination'); ?></h3>
                                        <p class="muted trip-route"><?php echo planner_escape($trip['origin']); ?> to <?php echo planner_escape($trip['trip_name']); ?></p>
                                    </div>
                                    <span class="stage-pill <?php echo planner_escape(travel_stage_badge_class($tripStage)); ?>">
                                        <?php echo planner_escape(travel_format_stage($tripStage)); ?>
                                    </span>
                                </div>
                                <div class="trip-meta">
                                    <span><?php echo planner_escape(travel_format_trip_date((string) ($trip['trip_date_value'] ?? ''), (string) ($trip['created_at'] ?? ''))); ?></span>
                                    <span>Order <?php echo planner_escape($trip['sequence_order'] ?? 1); ?></span>
                                    <span><?php echo planner_escape(travel_format_mode((string) $trip['transport_mode'])); ?></span>
                                    <span><?php echo planner_escape($trip['distance_km']); ?> km</span>
                                    <span><?php echo planner_escape($trip['total_carbon_est']); ?> kg CO2</span>
                                    <?php if (isset($trip['eco_rating'])): ?>
                                        <span>Eco rating <?php echo planner_escape($trip['eco_rating']); ?>/5</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($trip['destination_id'])): ?>
                                    <p class="field-help"><a href="destination_detail.php?id=<?php echo planner_escape($trip['destination_id']); ?>">Open destination goal</a> to edit or manage this segment.</p>
                                <?php endif; ?>
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
<<<<<<< HEAD
</html>
=======
</html>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
