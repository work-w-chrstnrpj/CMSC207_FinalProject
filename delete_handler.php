<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_destination') {
    $destId = (int) ($_POST['dest_id'] ?? 0);

    if ($destId > 0) {
        // Delete all trips and itinerary items for this destination
        $getTripsStmt = $conn->prepare("SELECT trip_id FROM itinerary_items WHERE dest_id = ?");
        $getTripsStmt->bind_param('i', $destId);
        $getTripsStmt->execute();
        $tripsResult = $getTripsStmt->get_result();

        while ($tripRow = $tripsResult->fetch_assoc()) {
            $tripId = $tripRow['trip_id'];

            // Delete itinerary items for this trip
            $deleteItemsStmt = $conn->prepare("DELETE FROM itinerary_items WHERE trip_id = ?");
            $deleteItemsStmt->bind_param('i', $tripId);
            $deleteItemsStmt->execute();
            $deleteItemsStmt->close();

            // Delete trip
            $deleteTripStmt = $conn->prepare("DELETE FROM trips WHERE trip_id = ?");
            $deleteTripStmt->bind_param('i', $tripId);
            $deleteTripStmt->execute();
            $deleteTripStmt->close();
        }

        $getTripsStmt->close();

        // Delete the destination itself
        $destIdColumn = 'id';
        $columns = $conn->query('SHOW COLUMNS FROM destinations');
        if ($columns) {
            while ($row = $columns->fetch_assoc()) {
                if ($row['Field'] === 'dest_id') {
                    $destIdColumn = 'dest_id';
                    break;
                }
            }
        }

        $deleteDestStmt = $conn->prepare("DELETE FROM destinations WHERE {$destIdColumn} = ?");
        $deleteDestStmt->bind_param('i', $destId);
        $deleteDestStmt->execute();
        $deleteDestStmt->close();
    }
}

header("Location: index.php");
exit();
?>
