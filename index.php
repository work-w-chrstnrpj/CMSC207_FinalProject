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

<<<<<<< HEAD
function dashboard_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboard_clean_destination_name(string $name): string
{
    $cleaned = preg_replace('/https?:\/\/\S+/i', '', $name) ?? $name;
    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);

    return $cleaned !== '' ? $cleaned : 'Destination';
}

destination_ensure_photo_column($conn);

=======
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
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
<<<<<<< HEAD
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! Build destination goals, then save the commute segments that belong to each one.</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="planner.php">Trip Segments</a>
=======
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="planner.php">Trip Planner</a>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="section-head">
<<<<<<< HEAD
                <h2>Destination Goals</h2>
                <a href="destination_form.php" class="button button-secondary" style="margin-left: auto;">+ Create Goal</a>
            </section>

            <?php if (isset($_GET['created'])): ?>
                <p class="message success">Destination goal created successfully.</p>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <p class="message success">Destination goal updated successfully.</p>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <p class="message success">Destination goal deleted successfully.</p>
            <?php endif; ?>

            <?php if ($destinationCount === 0): ?>
                <p style="color: #d43f3f; padding: 20px; text-align: center;">No destination goals found. Create one to get started!</p>
            <?php endif; ?>

            <div class="cards-grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $destId = $row['id'] ?? $row['dest_id'] ?? '';
                    $photoPath = destination_get_photo_public_path($row['photo_path'] ?? '');
                    $destinationName = dashboard_clean_destination_name((string) ($row['name'] ?? 'Destination'));
                    $destinationDetailUrl = 'destination_detail.php?id=' . urlencode((string) $destId);
                    ?>
                    <article class="card destination-card" role="link" tabindex="0" onclick="window.location.href='<?php echo dashboard_escape($destinationDetailUrl); ?>';" onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); window.location.href='<?php echo dashboard_escape($destinationDetailUrl); ?>'; }" aria-label="Open <?php echo dashboard_escape($destinationName); ?> details">
                        <div class="destination-card-image-wrapper">
                            <?php if ($photoPath !== ''): ?>
                                <img class="destination-card-image" src="<?php echo dashboard_escape($photoPath); ?>" alt="<?php echo dashboard_escape($destinationName); ?> photo">
                            <?php else: ?>
                                <div class="destination-card-placeholder" aria-hidden="true">
                                    <span><?php echo dashboard_escape(destination_placeholder_initials($destinationName)); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="destination-card-content">
                            <h3 class="destination-card-title"><?php echo dashboard_escape($destinationName); ?></h3>
                        </div>
                    </article>
=======
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
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>
<<<<<<< HEAD
</html>
=======
</html>
>>>>>>> eea1a3c55efd4bc65a2ae060eb5f694b773f040d
