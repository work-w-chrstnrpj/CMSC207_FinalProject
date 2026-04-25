<?php
session_start();
require 'db.php';
require 'destination_photo_helper.php';

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

function form_looks_like_image_url(string $url): bool {
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $path = (string) parse_url($url, PHP_URL_PATH);

    if ($path === '') {
        return false;
    }

    return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path) === 1;
}

function form_clean_destination_name(string $name): string {
    $cleaned = preg_replace('/https?:\/\/\S+/i', '', $name) ?? $name;
    return trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);
}

$destId = (int) ($_GET['id'] ?? 0);
$isEdit = $destId > 0;
$destination = null;
$formError = '';

destination_ensure_photo_column($conn);

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
    'transport_tips' => $destination['transport_tips'] ?? '',
    'photo_url' => destination_is_external_photo_path($destination['photo_path'] ?? '') ? ($destination['photo_path'] ?? '') : '',
];

$currentPhotoPath = destination_get_photo_public_path($destination['photo_path'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['name'] = form_clean_destination_name(trim($_POST['name'] ?? ''));
    $formValues['transport_tips'] = trim($_POST['transport_tips'] ?? '');
    $formValues['photo_url'] = trim($_POST['photo_url'] ?? '');
    $deletePhoto = isset($_POST['delete_photo']) && $_POST['delete_photo'] === '1';

    if (empty($formValues['name']) || empty($formValues['transport_tips'])) {
        $formError = 'Please fill in all fields.';
    } else {
        $existingPhotoPath = $destination['photo_path'] ?? '';
        $photoUrl = $formValues['photo_url'];

        if ($photoUrl !== '' && !form_looks_like_image_url($photoUrl)) {
            $formError = 'Photo URL must be a direct image link ending in .jpg, .jpeg, .png, .gif, or .webp.';
        }

        $photoUpload = destination_store_uploaded_photo($_FILES['destination_photo'] ?? []);

        if ($formError === '' && $photoUpload['status'] === 'error') {
            $formError = $photoUpload['message'];
        }

        if ($formError === '') {
            $photoPathForDb = $existingPhotoPath;
            $uploadedPhotoPath = '';

            if ($photoUpload['status'] === 'uploaded') {
                $uploadedPhotoPath = $photoUpload['path'];
                $photoPathForDb = $uploadedPhotoPath;
            } elseif ($deletePhoto) {
                $photoPathForDb = '';
            } elseif ($photoUrl !== '') {
                $photoPathForDb = $photoUrl;
            }

            $photoChanged = $photoPathForDb !== (string) $existingPhotoPath;

            if ($isEdit) {
                $name = $formValues['name'];
                $tips = $formValues['transport_tips'];
                $updateStmt = $conn->prepare("UPDATE destinations SET name = ?, transport_tips = ?, photo_path = NULLIF(?, '') WHERE {$destIdColumn} = ?");
                $updateStmt->bind_param('sssi', $name, $tips, $photoPathForDb, $destId);
                if ($updateStmt->execute()) {
                    if ($photoChanged && $existingPhotoPath !== '') {
                        destination_delete_photo_file($existingPhotoPath);
                    }
                    $updateStmt->close();
                    header("Location: index.php?updated=1");
                    exit();
                } else {
                    if ($uploadedPhotoPath !== '') {
                        destination_delete_photo_file($uploadedPhotoPath);
                    }

                    $formError = 'Error updating destination goal: ' . $updateStmt->error;
                }
                $updateStmt->close();
            } else {
                $name = $formValues['name'];
                $tips = $formValues['transport_tips'];

                if ($destIdColumn === 'dest_id' && str_contains($destIdType, 'char') && !str_contains($destIdExtra, 'auto_increment')) {
                    $newDestId = form_generate_uuid_v4();
                    $insertStmt = $conn->prepare("INSERT INTO destinations (dest_id, name, transport_tips, photo_path) VALUES (?, ?, ?, NULLIF(?, ''))");
                    $insertStmt->bind_param('ssss', $newDestId, $name, $tips, $photoPathForDb);
                } else {
                    $insertStmt = $conn->prepare("INSERT INTO destinations (name, transport_tips, photo_path) VALUES (?, ?, NULLIF(?, ''))");
                    $insertStmt->bind_param('sss', $name, $tips, $photoPathForDb);
                }

                if ($insertStmt->execute()) {
                    header("Location: index.php?created=1");
                    exit();
                } else {
                    if ($uploadedPhotoPath !== '') {
                        destination_delete_photo_file($uploadedPhotoPath);
                    }

                    $formError = 'Error creating destination goal: ' . $insertStmt->error;
                }
                $insertStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Destination Goal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="container" style="max-width: 600px;">
            <header class="topbar">
                <div>
                    <h1><?php echo $isEdit ? 'Edit' : 'Create'; ?> Destination Goal</h1>
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

                <form method="POST" enctype="multipart/form-data" class="planner-form">
                    <div class="field-grid">
                        <div class="field field-full">
                            <label for="name">Destination Goal Name</label>
                            <input type="text" id="name" name="name" placeholder="Destination goal name" value="<?php echo form_escape($formValues['name']); ?>" required>
                        </div>

                        <div class="field field-full">
                            <label for="transport_tips">Transport Notes for This Goal</label>
                            <textarea id="transport_tips" name="transport_tips" placeholder="Describe sustainable ways to get there or move around once you arrive..." rows="4" required><?php echo form_escape($formValues['transport_tips']); ?></textarea>
                        </div>

                        <div class="field field-full">
                            <label for="destination_photo">Destination Goal Photo</label>
                            <input class="file-input" type="file" id="destination_photo" name="destination_photo" accept=".jpg,.jpeg,.png,.webp,.gif">
                            <p class="field-help">Upload a JPG, PNG, WEBP, or GIF image up to 5 MB. Adding a new file will replace the current one.</p>
                        </div>

                        <div class="field field-full">
                            <label for="photo_url">Or use a direct image URL</label>
                            <input type="text" id="photo_url" name="photo_url" placeholder="https://your-image-link.com/photo.jpg" value="<?php echo form_escape($formValues['photo_url']); ?>">
                            <p class="field-help">Optional. If both file and URL are provided, uploaded file is used.</p>
                        </div>

                        <?php if ($currentPhotoPath !== ''): ?>
                            <div class="field field-full">
                                <div class="destination-photo-manager">
                                    <div class="destination-photo-preview">
                                        <img src="<?php echo form_escape($currentPhotoPath); ?>" alt="<?php echo form_escape($formValues['name'] ?: 'Destination'); ?> photo">
                                    </div>
                                    <label class="checkbox-row" for="delete_photo">
                                        <input type="checkbox" id="delete_photo" name="delete_photo" value="1">
                                        Remove the current photo
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit"><?php echo $isEdit ? 'Update' : 'Create'; ?> Destination Goal</button>

                    <?php if ($isEdit): ?>
                        <a href="destination_detail.php?id=<?php echo $destId; ?>" class="button button-secondary" style="display: block; text-align: center; margin-top: 12px;">Cancel</a>
                    <?php endif; ?>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
