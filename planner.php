<?php
session_start();
require 'db.php';
require 'trip_segment_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function planner_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

travel_ensure_trip_tables($conn);

$destinationIdColumn = travel_get_destination_id_column($conn);
$transportModes = travel_get_transport_modes();
$stageOptions = travel_get_stage_options();

$destinations = [];
$destinationsResult = $conn->query("SELECT * FROM destinations ORDER BY name ASC");

if ($destinationsResult) {
    while ($row = $destinationsResult->fetch_assoc()) {
        $did = $row['id'] ?? $row['dest_id'] ?? 0;
        if ($did) {
            $row['eco_rating'] = travel_calculate_eco_rating($conn, $did);
        }
        $destinations[] = $row;
    }

    $destinationsResult->close();
}

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

$plannerError = '';
$plannerSuccess = '';
$plannerResult = null;
$routeOrderHelpText = 'Choose a destination goal and trip date to preview the next route order.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
    $formValues['destination_id'] = trim($_POST['destination_id'] ?? '');
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
    } else {
        $destinationStmt = $conn->prepare("SELECT * FROM destinations WHERE {$destinationIdColumn} = ? LIMIT 1");
        $destinationStmt->bind_param('i', $destinationId);
        $destinationStmt->execute();
        $destinationResult = $destinationStmt->get_result();
        $destination = $destinationResult ? $destinationResult->fetch_assoc() : null;
        $destinationStmt->close();

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

            if ($tripStmt->execute()) {
                $tripId = $conn->insert_id;
                $tripStmt->close();

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
                $plannerResult = [
                    'trip_name' => $formValues['trip_name'],
                    'origin' => $formValues['origin'],
                    'destination_name' => $destination['name'] ?? 'Selected destination',
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
                $tripStmt->close();
            }
        }
    }
}

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
$savedTripsStmt->bind_param('i', $_SESSION['user_id']);
$savedTripsStmt->execute();
$savedTripsResult = $savedTripsStmt->get_result();

if ($savedTripsResult) {
    while ($row = $savedTripsResult->fetch_assoc()) {
        $savedTrips[] = $row;
    }
}

$savedTripsStmt->close();

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Trip Segments</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
                    <h1>Destination Trip Segments</h1>
                    <p>Add one commute fare or route segment at a time under a destination goal.</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Dashboard</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <div class="planner-layout">
                <section class="panel card">
                    <h2>Add a Trip Segment</h2>
                    <p class="panel-lead">Each trip here should be one fare or one route leg under a destination goal.</p>
                    <p class="helper-note">Choose the destination goal first, then save each segment that belongs to that goal.</p>

                    <?php if ($plannerError !== ''): ?>
                        <p class="message error"><?php echo planner_escape($plannerError); ?></p>
                    <?php endif; ?>

                    <?php if ($plannerSuccess !== ''): ?>
                        <p class="message success"><?php echo planner_escape($plannerSuccess); ?></p>
                    <?php endif; ?>

                    <form method="POST" class="planner-form trip-segment-form">
                        <div class="field-grid trip-segment-grid">
                            <div class="field field-full">
                                <label for="destination_id">Destination Goal</label>
                                <select id="destination_id" name="destination_id" required>
                                    <option value="">Select a destination goal</option>
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
                                <select id="transport_mode" name="transport_mode">
                                    <option value="auto" <?php echo $formValues['transport_mode'] === 'auto' ? 'selected' : ''; ?>>Auto recommend the best option</option>
                                    <?php foreach ($transportModes as $modeKey => $modeData): ?>
                                        <option value="<?php echo planner_escape($modeKey); ?>" <?php echo $formValues['transport_mode'] === $modeKey ? 'selected' : ''; ?>>
                                            <?php echo planner_escape($modeData['label']); ?> - <?php echo planner_escape($modeData['badge']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                    </form>
                </section>

                <aside class="panel card">
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
                        </div>
                    </div>

                    <?php if ($plannerResult !== null): ?>
                        <div class="result-box">
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
                            </div>
                            <p class="planner-tip"><strong>Recommendation:</strong> <?php echo planner_escape($plannerResult['recommended_reason']); ?></p>
                            <p class="planner-tip"><strong>Transport guidance:</strong> <?php echo planner_escape($plannerResult['selected_mode_tip']); ?></p>
                            <p class="planner-tip"><strong>Destination note:</strong> <?php echo planner_escape($plannerResult['destination_tips']); ?></p>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <section class="panel card trip-history">
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
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
</html>
