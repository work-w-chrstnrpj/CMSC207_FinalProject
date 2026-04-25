<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$tripId = (int) ($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);
$destId = (int) ($_POST['dest_id'] ?? $_GET['dest_id'] ?? 0);

if ($action === 'delete' && $tripId > 0) {
<<<<<<< HEAD
    $ownedTripStmt = $conn->prepare("SELECT trip_id FROM trips WHERE trip_id = ? AND user_id = ? LIMIT 1");
    $ownedTripStmt->bind_param('ii', $tripId, $_SESSION['user_id']);
    $ownedTripStmt->execute();
    $ownedTripResult = $ownedTripStmt->get_result();
    $ownedTrip = $ownedTripResult ? $ownedTripResult->fetch_assoc() : null;
    $ownedTripStmt->close();

    if ($ownedTrip) {
        $deleteStmt = $conn->prepare("DELETE FROM itinerary_items WHERE trip_id = ?");
        $deleteStmt->bind_param('i', $tripId);
        $deleteStmt->execute();
        $deleteStmt->close();

        $deleteTripStmt = $conn->prepare("DELETE FROM trips WHERE trip_id = ? AND user_id = ?");
        $deleteTripStmt->bind_param('ii', $tripId, $_SESSION['user_id']);
        $deleteTripStmt->execute();
        $deleteTripStmt->close();
    }
=======
    $deleteStmt = $conn->prepare("DELETE FROM itinerary_items WHERE trip_id = ?");
    $deleteStmt->bind_param('i', $tripId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $deleteTripStmt = $conn->prepare("DELETE FROM trips WHERE trip_id = ?");
    $deleteTripStmt->bind_param('i', $tripId);
    $deleteTripStmt->execute();
    $deleteTripStmt->close();
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d

    if ($destId > 0) {
        header("Location: destination_detail.php?id=" . urlencode($destId));
    } else {
        header("Location: planner.php");
    }
    exit();
}

header("Location: index.php");
exit();
?>
