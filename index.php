<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$result = $conn->query("SELECT * FROM destinations ORDER BY name ASC");

if (!$result) {
    die('Database query error: ' . $conn->error);
}

$destinationCount = $result->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sustainable Travel Planner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container">
            <header class="topbar">
                <div>
                    <h1>Sustainable Travel Planner</h1>
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="planner.php">Trip Planner</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="section-head">
                <h2>Eco-Friendly Destinations</h2>
                <a href="destination_form.php" class="button button-secondary" style="margin-left: auto;">+ Create Destination</a>
            </section>

            <?php if ($destinationCount === 0): ?>
                <p style="color: #d43f3f; padding: 20px; text-align: center;">No destinations found. Create one to get started!</p>
            <?php endif; ?>

            <div class="cards-grid">
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php $destId = $row['id'] ?? $row['dest_id'] ?? ''; ?>
                    <a href="destination_detail.php?id=<?php echo urlencode($destId); ?>" class="card card-link">
                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p class="rating">Eco-Rating: <?php echo $row['eco_rating']; ?> / 5</p>
                        <p><strong>Sustainable Transport Tip:</strong> <?php echo htmlspecialchars($row['transport_tips']); ?></p>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>
</html>