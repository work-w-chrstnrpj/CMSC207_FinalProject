<?php
session_start();
require 'db.php';
require 'destination_photo_helper.php';
require 'trip_segment_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$genericQuickTips = [
    'Save every transfer separately so your route stays realistic.',
    'Plan ahead to find better transit options.',
    'Consider traveling during off-peak hours to reduce congestion.',
    'Combine nearby errands or stops into one smoother route.',
    'Choose accommodations within walking distance of attractions.',
    'Use public transportation apps to plan efficient local rides.',
];

function detail_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

travel_ensure_trip_tables($conn);
travel_ensure_destination_note_table($conn);
destination_ensure_photo_column($conn);

$transportModes = travel_get_transport_modes();
$stageOptions = travel_get_stage_options();
$destinationIdColumn = travel_get_destination_id_column($conn);
$destId = (int) ($_GET['id'] ?? 0);

if ($destId <= 0) {
    header("Location: index.php");
    exit();
}

$destStmt = $conn->prepare("SELECT * FROM destinations WHERE {$destinationIdColumn} = ? LIMIT 1");
$destStmt->bind_param('i', $destId);
$destStmt->execute();
$destResult = $destStmt->get_result();
$destination = $destResult ? $destResult->fetch_assoc() : null;
$destStmt->close();

if ($destination) {
    $destination['eco_rating'] = travel_calculate_eco_rating($conn, $destId);
}

if (!$destination) {
    header("Location: index.php");
    exit();
}

$destinationPhotoPath = destination_get_photo_public_path($destination['photo_path'] ?? '');
$destinationName = (string) ($destination['name'] ?? 'Destination');
$pageAction = (string) ($_POST['action'] ?? '');
$detailError = '';
$detailSuccess = isset($_GET['trip_updated']) ? 'Trip segment updated successfully.' : '';
$noteError = '';
$noteSuccess = '';
$personalNotes = travel_fetch_destination_notes($conn, (int) $_SESSION['user_id'], $destId);

// Handle specific note edit
$editNoteId = isset($_GET['edit_note']) ? (int)$_GET['edit_note'] : 0;
$editNoteMode = $editNoteId > 0;
$noteValue = '';

if ($editNoteMode) {
    // Find the specific note being edited
    foreach ($personalNotes as $note) {
        if ((int)$note['note_id'] === $editNoteId) {
            $noteValue = (string) ($note['note_text'] ?? '');
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageAction === 'delete_destination') {
    if (($_POST['confirm_delete'] ?? '') === 'yes') {
        $getTripsStmt = $conn->prepare("SELECT trip_id FROM itinerary_items WHERE dest_id = ?");
        $getTripsStmt->bind_param('i', $destId);
        $getTripsStmt->execute();
        $tripsResult = $getTripsStmt->get_result();

        while ($tripRow = $tripsResult->fetch_assoc()) {
            $tripId = (int) ($tripRow['trip_id'] ?? 0);

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

        $deleteNotesStmt = $conn->prepare("DELETE FROM destination_personal_notes WHERE dest_id = ?");
        $deleteNotesStmt->bind_param('i', $destId);
        $deleteNotesStmt->execute();
        $deleteNotesStmt->close();

        $deleteDestStmt = $conn->prepare("DELETE FROM destinations WHERE {$destinationIdColumn} = ?");
        $deleteDestStmt->bind_param('i', $destId);
        $deleteSuccessful = $deleteDestStmt->execute();
        $deleteDestStmt->close();

        if ($deleteSuccessful) {
            destination_delete_photo_file($destination['photo_path'] ?? '');
            header("Location: index.php?deleted=1");
            exit();
        }

        $detailError = 'Unable to delete this destination goal right now. Please try again.';
    }
}

$formValues = [
    'trip_name' => '',
    'origin' => '',
    'trip_date' => travel_get_default_trip_date(),
    'distance_km' => '',
    'transport_mode' => 'auto',
    'trip_stage' => 'to_destination',
    'sequence_order' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageAction === 'save_note') {
    $noteValue = trim($_POST['note_text'] ?? '');
    $noteId = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

    if ($noteValue === '') {
        $noteError = 'Please write a note before saving, or use delete if you want to remove it.';
        $editNoteMode = $noteId > 0;
    } else {
        if ($noteId > 0) {
            $noteStmt = $conn->prepare('UPDATE destination_personal_notes SET note_text = ? WHERE user_id = ? AND dest_id = ? AND note_id = ?');
            $noteStmt->bind_param('siii', $noteValue, $_SESSION['user_id'], $destId, $noteId);
            $noteSaved = $noteStmt->execute();
            $noteStmt->close();
            $noteSuccess = $noteSaved ? 'Personal note updated successfully.' : '';
        } else {
            $noteStmt = $conn->prepare('INSERT INTO destination_personal_notes (user_id, dest_id, note_text) VALUES (?, ?, ?)');
            $noteStmt->bind_param('iis', $_SESSION['user_id'], $destId, $noteValue);
            $noteSaved = $noteStmt->execute();
            $noteStmt->close();
            $noteSuccess = $noteSaved ? 'Personal note saved successfully.' : '';
        }

        if ($noteSuccess === '') {
            $noteError = 'Unable to save your personal note right now. Please try again.';
            $editNoteMode = $noteId > 0;
        } else {
            $personalNotes = travel_fetch_destination_notes($conn, (int) $_SESSION['user_id'], $destId);
            $editNoteMode = false;
            $noteValue = '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageAction === 'delete_note') {
    $noteIdToDelete = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
    $deleteNoteStmt = $conn->prepare('DELETE FROM destination_personal_notes WHERE user_id = ? AND dest_id = ? AND note_id = ?');
    $deleteNoteStmt->bind_param('iii', $_SESSION['user_id'], $destId, $noteIdToDelete);
    $noteDeleted = $deleteNoteStmt->execute();
    $deleteNoteStmt->close();

    if ($noteDeleted) {
        $personalNotes = travel_fetch_destination_notes($conn, (int) $_SESSION['user_id'], $destId);
        $editNoteMode = false;
        $noteValue = '';
        $noteSuccess = 'Personal note deleted successfully.';
    } else {
        $noteError = 'Unable to delete your personal note right now. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($pageAction === 'save_trip' || $pageAction === '')) {
    $formValues['trip_name'] = trim($_POST['trip_name'] ?? '');
    $formValues['origin'] = trim($_POST['origin'] ?? '');
    $formValues['trip_date'] = trim($_POST['trip_date'] ?? travel_get_default_trip_date());
    $formValues['distance_km'] = trim($_POST['distance_km'] ?? '');
    $formValues['transport_mode'] = trim($_POST['transport_mode'] ?? 'auto');
    $formValues['trip_stage'] = trim($_POST['trip_stage'] ?? 'to_destination');
    $formValues['sequence_order'] = trim($_POST['sequence_order'] ?? '');

    $tripDate = $formValues['trip_date'];
    $distanceKm = (float) $formValues['distance_km'];
    $transportMode = $formValues['transport_mode'];
    $tripStage = $formValues['trip_stage'];
    $sequenceOrder = $formValues['sequence_order'] === '' ? 0 : (int) $formValues['sequence_order'];

    if ($formValues['trip_name'] === '' || $formValues['origin'] === '' || $tripDate === '' || $distanceKm <= 0) {
        $detailError = 'Please complete the from, to, trip date, and distance fields.';
    } elseif (!travel_is_valid_trip_date($tripDate)) {
        $detailError = 'Please choose a valid trip date.';
    } elseif (!array_key_exists($transportMode, $transportModes) && $transportMode !== 'auto') {
        $detailError = 'Please choose a valid transport mode.';
    } elseif (!array_key_exists($tripStage, $stageOptions)) {
        $detailError = 'Please choose a valid trip stage.';
    } elseif ($formValues['sequence_order'] !== '' && $sequenceOrder <= 0) {
        $detailError = 'Route order must be 1 or higher when you set it manually.';
    } else {
        if ($sequenceOrder <= 0) {
            $sequenceOrder = travel_get_next_sequence_order($conn, $destId, $tripStage, null, $tripDate);
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
                $destId,
                $calculation['selected_mode'],
                $calculation['is_eco_friendly'],
                $sequenceOrder,
                $distanceKm,
                $calculation['carbon_estimate'],
                $tripStage
            );
            $itemStmt->execute();
            $itemStmt->close();

            $detailSuccess = 'Trip segment saved successfully.';
            $formValues = [
                'trip_name' => '',
                'origin' => '',
                'trip_date' => travel_get_default_trip_date(),
                'distance_km' => '',
                'transport_mode' => 'auto',
                'trip_stage' => 'to_destination',
                'sequence_order' => '',
            ];
        } else {
            $detailError = 'Unable to save the trip segment right now. Please try again.';
            $tripStmt->close();
        }
    }
}

$tripsToDestination = [];
$tripsStmt = $conn->prepare(
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
        i.sequence_order
    FROM trips t
    INNER JOIN itinerary_items i ON i.trip_id = t.trip_id
    WHERE i.dest_id = ? AND t.user_id = ?
    ORDER BY
        COALESCE(t.trip_date, DATE(t.created_at)) ASC,
        CASE i.trip_stage
            WHEN 'to_destination' THEN 1
            WHEN 'inside_destination' THEN 2
            ELSE 3
        END,
        i.sequence_order ASC,
        t.created_at DESC"
);
$tripsStmt->bind_param('ii', $destId, $_SESSION['user_id']);
$tripsStmt->execute();
$tripsResult = $tripsStmt->get_result();

if ($tripsResult) {
    while ($row = $tripsResult->fetch_assoc()) {
        $tripsToDestination[] = $row;
    }
}

$tripsStmt->close();

$stageCounts = [
    'to_destination' => 0,
    'inside_destination' => 0,
];

foreach ($tripsToDestination as $trip) {
    $tripStage = (string) ($trip['trip_stage'] ?? 'to_destination');
    if (array_key_exists($tripStage, $stageCounts)) {
        $stageCounts[$tripStage]++;
    }
}

$routeOrderHelpText = travel_build_next_order_help($conn, $destId, $formValues['trip_date']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo detail_escape($destinationName); ?> - Destination Goal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
                    <h1><?php echo detail_escape($destinationName); ?></h1>
                    <p>This destination is your travel goal. Save every commute fare or route segment connected to it.</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Back to Dashboard</a>
                    <a class="button button-secondary" href="destination_form.php?id=<?php echo $destId; ?>">Edit Goal</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this destination goal and all associated trip segments? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_destination">
                        <input type="hidden" name="confirm_delete" value="yes">
                        <button type="submit" class="button btn-delete-dest">Delete Goal</button>
                    </form>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="destination-hero card">
                <div class="hero-content <?php echo $destinationPhotoPath !== '' ? 'hero-content-split' : ''; ?>">
                    <?php if ($destinationPhotoPath !== ''): ?>
                        <div class="hero-media">
                            <img src="<?php echo detail_escape($destinationPhotoPath); ?>" alt="<?php echo detail_escape($destinationName); ?> photo">
                        </div>
                    <?php endif; ?>
                    <div class="hero-main">
                        <h2>Destination Goal Overview</h2>
                        <p class="rating-badge">
                            <span class="rating">Eco rating: <?php echo detail_escape($destination['eco_rating']); ?> / 5</span>
                        </p>
                        <p class="eco-tips"><strong>Transport note for this goal:</strong><br><?php echo detail_escape($destination['transport_tips']); ?></p>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <span class="stat-label">Going there segments</span>
                                <strong class="stat-value"><?php echo detail_escape($stageCounts['to_destination']); ?></strong>
                            </div>
                            <div class="stat-card">
                                <span class="stat-label">Inside destination segments</span>
                                <strong class="stat-value"><?php echo detail_escape($stageCounts['inside_destination']); ?></strong>
                            </div>
                            <div class="stat-card">
                                <span class="stat-label">Total saved segments</span>
                                <strong class="stat-value"><?php echo detail_escape(count($tripsToDestination)); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="detail-layout">
                <section class="panel card">
                    <h2>Add a Trip Segment for <?php echo detail_escape($destinationName); ?></h2>
                    <p class="panel-lead">Save one fare or route leg at a time for this destination goal.</p>
                    <p class="helper-note">Leave route order blank if you want the app to place this as the next segment automatically for the selected date and stage.</p>

                    <?php if ($detailError !== ''): ?>
                        <p class="message error"><?php echo detail_escape($detailError); ?></p>
                    <?php endif; ?>

                    <?php if ($detailSuccess !== ''): ?>
                        <p class="message success"><?php echo detail_escape($detailSuccess); ?></p>
                    <?php endif; ?>

                    <form method="POST" class="planner-form trip-segment-form">
                        <input type="hidden" name="action" value="save_trip">

                        <div class="field-grid trip-segment-grid">
                            <div class="field">
                                <label for="origin">From</label>
                                <input type="text" id="origin" name="origin" placeholder="Starting point" value="<?php echo detail_escape($formValues['origin']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="trip_name">To</label>
                                <input type="text" id="trip_name" name="trip_name" placeholder="Arrival point" value="<?php echo detail_escape($formValues['trip_name']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="trip_stage">Trip Stage</label>
                                <select id="trip_stage" name="trip_stage" required>
                                    <?php foreach ($stageOptions as $stageKey => $stageData): ?>
                                        <option value="<?php echo detail_escape($stageKey); ?>" <?php echo $formValues['trip_stage'] === $stageKey ? 'selected' : ''; ?>>
                                            <?php echo detail_escape($stageData['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="distance_km">Distance in km</label>
                                <input type="number" id="distance_km" name="distance_km" min="0.1" step="0.1" placeholder="100" value="<?php echo detail_escape($formValues['distance_km']); ?>" required>
                            </div>

                            <div class="field">
                                <label for="transport_mode">Transport Mode</label>
                                <select id="transport_mode" name="transport_mode">
                                    <option value="auto" <?php echo $formValues['transport_mode'] === 'auto' ? 'selected' : ''; ?>>Auto recommend the best option</option>
                                    <?php foreach ($transportModes as $modeKey => $modeData): ?>
                                        <option value="<?php echo detail_escape($modeKey); ?>" <?php echo $formValues['transport_mode'] === $modeKey ? 'selected' : ''; ?>>
                                            <?php echo detail_escape($modeData['label']); ?> - <?php echo detail_escape($modeData['badge']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field-help field-help-balance">Leave as Auto to let the system recommend the most eco-friendly option based on distance.</p>
                            </div>

                            <div class="field">
                                <label for="sequence_order">Route Order</label>
                                <input type="number" id="sequence_order" name="sequence_order" min="1" step="1" placeholder="Auto" value="<?php echo detail_escape($formValues['sequence_order']); ?>">
                                <p class="field-help field-help-balance"><?php echo detail_escape($routeOrderHelpText); ?></p>
                            </div>

                            <div class="field field-full field-trip-date">
                                <label for="trip_date">Trip Date</label>
                                <input type="date" id="trip_date" name="trip_date" value="<?php echo detail_escape($formValues['trip_date']); ?>" required>
                            </div>
                        </div>

                        <button type="submit">Save trip segment</button>
                    </form>
                </section>

                <aside class="panel card" id="note-section">
                    <h2>Personal Note</h2>
                    <p class="panel-lead">Keep a private note for this destination goal.</p>
                    <p class="helper-note">Use this for reminders, terminal details, budget notes, checklists, or anything you want to remember.</p>

                    <?php if ($noteError !== ''): ?>
                        <p class="message error"><?php echo detail_escape($noteError); ?></p>
                    <?php endif; ?>

                    <?php if ($noteSuccess !== ''): ?>
                        <p class="message success"><?php echo detail_escape($noteSuccess); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($personalNotes)): ?>
                        <?php foreach ($personalNotes as $note): ?>
                            <div class="saved-note-card">
                                <div class="saved-note-head">
                                    <strong class="saved-note-title">Saved Note</strong>
                                    <div class="saved-note-actions">
                                        <a href="destination_detail.php?id=<?php echo detail_escape($destId); ?>&edit_note=<?php echo (int)$note['note_id']; ?>#note-section" class="btn-action btn-edit">Edit</a>
                                        <form method="POST" class="saved-note-inline-form" onsubmit="return confirm('Delete this personal note?');">
                                            <input type="hidden" name="action" value="delete_note">
                                            <input type="hidden" name="note_id" value="<?php echo (int)$note['note_id']; ?>">
                                            <button type="submit" class="btn-action btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="saved-note-body">
                                    <p class="saved-note-text"><?php echo nl2br(detail_escape((string) ($note['note_text'] ?? ''))); ?></p>
                                    <p class="field-help note-meta">Last updated <?php echo detail_escape(date('M d, Y h:i A', strtotime((string) $note['updated_at']))); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <form method="POST" class="planner-form note-form">
                        <input type="hidden" name="action" value="save_note">
                        <?php if ($editNoteMode && $editNoteId > 0): ?>
                            <input type="hidden" name="note_id" value="<?php echo (int)$editNoteId; ?>">
                        <?php endif; ?>

                        <div class="field field-full">
                            <label for="note_text"><?php echo $editNoteMode ? 'Edit Note' : 'New Note'; ?></label>
                            <textarea id="note_text" name="note_text" class="note-textarea" rows="10" placeholder="Write your personal note for this destination here..."><?php echo detail_escape($noteValue); ?></textarea>
                        </div>

                        <button type="submit"><?php echo $editNoteMode ? 'Update Note' : 'Save Note'; ?></button>
                        <?php if ($editNoteMode): ?>
                            <a href="destination_detail.php?id=<?php echo detail_escape($destId); ?>#note-section" class="btn-action">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </aside>
            </div>

            <section class="panel card trip-history">
                <h2>Trip Segments for <?php echo detail_escape($destinationName); ?></h2>
                <?php if (empty($tripsToDestination)): ?>
                    <p class="muted">No trip segments saved for this destination goal yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="trip-table">
                            <thead>
                                <tr>
                                    <th>Stage</th>
                                    <th>Order</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Distance</th>
                                    <th>Transport</th>
                                    <th>Carbon</th>
                                    <th>Eco</th>
                                    <th>Date</th>
                                    <th colspan="2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tripsToDestination as $trip): ?>
                                    <?php $tripStage = (string) ($trip['trip_stage'] ?? 'to_destination'); ?>
                                    <tr>
                                        <td>
                                            <span class="stage-pill <?php echo detail_escape(travel_stage_badge_class($tripStage)); ?>">
                                                <?php echo detail_escape(travel_format_stage($tripStage)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo detail_escape($trip['sequence_order'] ?? 1); ?></td>
                                        <td><?php echo detail_escape($trip['origin']); ?></td>
                                        <td><?php echo detail_escape($trip['trip_name']); ?></td>
                                        <td><?php echo detail_escape($trip['distance_km']); ?> km</td>
                                        <td><?php echo detail_escape(travel_format_mode((string) $trip['transport_mode'])); ?></td>
                                        <td><?php echo detail_escape($trip['total_carbon_est']); ?> kg CO2</td>
                                        <td>
                                            <span class="status-pill <?php echo !empty($trip['is_eco_friendly']) ? 'status-eco' : 'status-neutral'; ?>">
                                                <?php echo !empty($trip['is_eco_friendly']) ? 'Lower impact' : 'Higher impact'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo detail_escape(travel_format_trip_date((string) ($trip['trip_date_value'] ?? ''), (string) ($trip['created_at'] ?? ''))); ?></td>
                                        <td>
                                            <a href="trip_edit.php?id=<?php echo $trip['trip_id']; ?>" class="btn-action btn-edit">Edit</a>
                                        </td>
                                        <td>
                                            <a href="trip_handler.php?action=delete&trip_id=<?php echo $trip['trip_id']; ?>&dest_id=<?php echo $destId; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this trip segment?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel card quick-tips-section">
                <h2>Quick Planning Tips</h2>
                <p class="panel-lead">Helpful reminders while breaking this goal into real commute segments:</p>
                <div class="tips-grid">
                    <?php foreach ($genericQuickTips as $index => $tip): ?>
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
