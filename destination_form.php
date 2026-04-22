<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function form_escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function form_generate_uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$destId = (int) ($_GET['id'] ?? 0);
$isEdit = $destId > 0;
$destination = null;
$formError = '';
$formSuccess = '';

// Get destination ID column name
$destIdColumn = 'id';
$destIdType = '';
$destIdExtra = '';
$columns = $conn->query('SHOW COLUMNS FROM destinations');
if ($columns) {
    while ($row = $columns->fetch_assoc()) {
        if ($row['Field'] === 'dest_id') {
            $destIdColumn = 'dest_id';
            $destIdType = strtolower((string) ($row['Type'] ?? ''));
            $destIdExtra = strtolower((string) ($row['Extra'] ?? ''));
            break;
        }
    }
}

if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM destinations WHERE {$destIdColumn} = ? LIMIT 1");
    $stmt->bind_param('i', $destId);
    $stmt->execute();
    $result = $stmt->get_result();
    $destination = $result->fetch_assoc();
    $stmt->close();

    if (!$destination) {
        header("Location: index.php");
        exit();
    }
}

$formValues = [
    'name' => $destination['name'] ?? '',
    'eco_rating' => $destination['eco_rating'] ?? 3,
    'transport_tips' => $destination['transport_tips'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['name'] = trim($_POST['name'] ?? '');
    $formValues['eco_rating'] = (int) ($_POST['eco_rating'] ?? 3);
    $formValues['transport_tips'] = trim($_POST['transport_tips'] ?? '');

    if (empty($formValues['name']) || empty($formValues['transport_tips'])) {
        $formError = 'Please fill in all fields.';
    } elseif ($formValues['eco_rating'] < 1 || $formValues['eco_rating'] > 5) {
        $formError = 'Eco rating must be between 1 and 5.';
    } else {
        if ($isEdit) {
            $name = $formValues['name'];
            $rating = $formValues['eco_rating'];
            $tips = $formValues['transport_tips'];
            $updateStmt = $conn->prepare("UPDATE destinations SET name = ?, eco_rating = ?, transport_tips = ? WHERE {$destIdColumn} = ?");
            $updateStmt->bind_param('sisi', $name, $rating, $tips, $destId);
            if ($updateStmt->execute()) {
                $formSuccess = 'Destination updated successfully!';
                $destination = ['name' => $formValues['name'], 'eco_rating' => $formValues['eco_rating'], 'transport_tips' => $formValues['transport_tips']];
            } else {
                $formError = 'Error updating destination: ' . $updateStmt->error;
            }
            $updateStmt->close();
        } else {
            $name = $formValues['name'];
            $rating = $formValues['eco_rating'];
            $tips = $formValues['transport_tips'];

            if ($destIdColumn === 'dest_id' && str_contains($destIdType, 'char') && !str_contains($destIdExtra, 'auto_increment')) {
                $newDestId = form_generate_uuid_v4();
                $insertStmt = $conn->prepare("INSERT INTO destinations (dest_id, name, eco_rating, transport_tips) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param('ssis', $newDestId, $name, $rating, $tips);
            } else {
                $insertStmt = $conn->prepare("INSERT INTO destinations (name, eco_rating, transport_tips) VALUES (?, ?, ?)");
                $insertStmt->bind_param('sis', $name, $rating, $tips);
            }

            if ($insertStmt->execute()) {
                header("Location: index.php?created=1");
                exit();
            } else {
                $formError = 'Error creating destination: ' . $insertStmt->error;
            }
            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Destination</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container" style="max-width: 600px;">
            <header class="topbar">
                <div>
                    <h1><?php echo $isEdit ? 'Edit' : 'Create'; ?> Destination</h1>
                </div>
                <div class="topbar-actions">
                    <a class="button button-secondary" href="index.php">Back</a>
                    <a class="button" href="logout.php">Logout</a>
                </div>
            </header>

            <section class="panel card">
                <?php if ($formError): ?>
                    <p class="message error"><?php echo form_escape($formError); ?></p>
                <?php endif; ?>

                <?php if ($formSuccess): ?>
                    <p class="message success"><?php echo form_escape($formSuccess); ?></p>
                <?php endif; ?>

                <form method="POST" class="planner-form">
                    <div class="field-grid">
                        <div class="field field-full">
                            <label for="name">Destination Name</label>
                            <input type="text" id="name" name="name" placeholder="e.g., Costa Rica, Nepal" value="<?php echo form_escape($formValues['name']); ?>" required>
                        </div>

                        <div class="field field-full">
                            <label for="eco_rating">Eco-Rating (1-5)</label>
                            <select id="eco_rating" name="eco_rating" required>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $formValues['eco_rating'] == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> / 5
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="field field-full">
                            <label for="transport_tips">Sustainable Transport Tip</label>
                            <textarea id="transport_tips" name="transport_tips" placeholder="Describe eco-friendly transport options at this destination..." rows="4" required><?php echo form_escape($formValues['transport_tips']); ?></textarea>
                        </div>
                    </div>

                    <button type="submit"><?php echo $isEdit ? 'Update' : 'Create'; ?> Destination</button>

                    <?php if ($isEdit): ?>
                        <a href="destination_detail.php?id=<?php echo $destId; ?>" class="button button-secondary" style="display: block; text-align: center; margin-top: 12px;">Cancel</a>
                    <?php endif; ?>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
