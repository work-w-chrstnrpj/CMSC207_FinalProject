<?php
session_start();
require 'db.php';
<<<<<<< HEAD
require 'destination_photo_helper.php';
=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_destination') {
    $destId = (int) ($_POST['dest_id'] ?? 0);

    if ($destId > 0) {
<<<<<<< HEAD
        destination_ensure_photo_column($conn);

=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
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

<<<<<<< HEAD
        $destinationStmt = $conn->prepare("SELECT photo_path FROM destinations WHERE {$destIdColumn} = ? LIMIT 1");
        $destinationStmt->bind_param('i', $destId);
        $destinationStmt->execute();
        $destinationResult = $destinationStmt->get_result();
        $destination = $destinationResult ? $destinationResult->fetch_assoc() : null;
        $destinationStmt->close();

        $deleteDestStmt = $conn->prepare("DELETE FROM destinations WHERE {$destIdColumn} = ?");
        $deleteDestStmt->bind_param('i', $destId);
        $deleteSuccessful = $deleteDestStmt->execute();
        $deleteDestStmt->close();

        if ($deleteSuccessful && $destination) {
            destination_delete_photo_file($destination['photo_path'] ?? '');
        }
=======
        $deleteDestStmt = $conn->prepare("DELETE FROM destinations WHERE {$destIdColumn} = ?");
        $deleteDestStmt->bind_param('i', $destId);
        $deleteDestStmt->execute();
        $deleteDestStmt->close();
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
    }
}

header("Location: index.php");
exit();
?>
