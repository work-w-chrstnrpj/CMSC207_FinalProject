<?php

function travel_add_column_if_missing(mysqli $conn, string $table, string $column, string $alterSql): void
{
    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '" . $conn->real_escape_string($safeColumn) . "'");

    if ($result && $result->num_rows === 0) {
        $conn->query($alterSql);
    }

    if ($result instanceof mysqli_result) {
        $result->close();
    }
}

function travel_get_destination_id_column(mysqli $conn): string
{
    $column = 'id';
    $columns = $conn->query('SHOW COLUMNS FROM destinations');

    if ($columns) {
        while ($row = $columns->fetch_assoc()) {
            if (($row['Field'] ?? '') === 'dest_id') {
                $columns->close();
                return 'dest_id';
            }

            if (($row['Field'] ?? '') === 'id') {
                $column = 'id';
            }
        }

        $columns->close();
    }

    return $column;
}

function travel_ensure_trip_tables(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS trips (
            trip_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            trip_name VARCHAR(150) NOT NULL,
            origin VARCHAR(150) NOT NULL,
            trip_date DATE NULL,
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
            trip_stage VARCHAR(30) NOT NULL DEFAULT \'to_destination\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (trip_id),
            INDEX (dest_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    travel_add_column_if_missing(
        $conn,
        'trips',
        'trip_date',
        'ALTER TABLE trips ADD COLUMN trip_date DATE NULL AFTER origin'
    );

    $conn->query('UPDATE trips SET trip_date = DATE(created_at) WHERE trip_date IS NULL');

    travel_add_column_if_missing(
        $conn,
        'itinerary_items',
        'trip_stage',
        "ALTER TABLE itinerary_items ADD COLUMN trip_stage VARCHAR(30) NOT NULL DEFAULT 'to_destination' AFTER carbon_est"
    );
}

function travel_ensure_destination_note_table(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS destination_personal_notes (
            note_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dest_id INT NOT NULL,
            note_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (dest_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    
    // Safely drop the unique index if it exists from a previous version
    try {
        $conn->query('ALTER TABLE destination_personal_notes DROP INDEX unique_user_destination_note');
    } catch (Exception $e) {
        // Ignore the error if the index does not exist
    }
}

function travel_fetch_destination_notes(mysqli $conn, int $userId, int $destId): array
{
    $stmt = $conn->prepare('SELECT note_id, note_text, created_at, updated_at FROM destination_personal_notes WHERE user_id = ? AND dest_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('ii', $userId, $destId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
    }
    $stmt->close();

    return $notes;
}

function travel_get_default_trip_date(): string
{
    return date('Y-m-d');
}

function travel_is_valid_trip_date(string $tripDate): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $tripDate);
    return $date instanceof DateTime && $date->format('Y-m-d') === $tripDate;
}

function travel_get_trip_date_value(?string $tripDate, ?string $createdAt = null): string
{
    if (is_string($tripDate) && $tripDate !== '' && travel_is_valid_trip_date($tripDate)) {
        return $tripDate;
    }

    if (is_string($createdAt) && $createdAt !== '') {
        $timestamp = strtotime($createdAt);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }

    return travel_get_default_trip_date();
}

function travel_format_trip_date(?string $tripDate, ?string $createdAt = null): string
{
    return date('M d, Y', strtotime(travel_get_trip_date_value($tripDate, $createdAt)));
}

function travel_get_transport_modes(): array
{
    return [
        'bike' => [
            'label' => 'Bike',
            'factor' => 0.00,
            'eco' => true,
            'badge' => 'Best for short city trips',
            'tip' => 'Choose bike routes, shared bike docks, and low-traffic streets.',
        ],
        'electric_bike' => [
            'label' => 'Electric Bikes',
            'factor' => 0.01,
            'eco' => true,
            'badge' => 'Excellent for urban commuting',
            'tip' => 'Very low operational impact; great for navigating city streets.',
        ],
        'electric_trike' => [
            'label' => 'Electric Trikes',
            'factor' => 0.02,
            'eco' => true,
            'badge' => 'Stable and eco-friendly local ride',
            'tip' => 'Ideal for short distances and carrying light cargo.',
        ],
        'taxi' => [
            'label' => 'Taxi',
            'factor' => 0.18,
            'eco' => false,
            'badge' => 'Convenient but higher emissions per person',
            'tip' => 'Consider rideshare or public transit if available.',
        ],
        'van' => [
            'label' => 'Van',
            'factor' => 0.15,
            'eco' => false,
            'badge' => 'Good for group travel',
            'tip' => 'Shared vans reduce per-passenger emissions compared to individual cars.',
        ],
        'bus' => [
            'label' => 'Bus',
            'factor' => 0.08,
            'eco' => true,
            'badge' => 'Efficient for regional travel',
            'tip' => 'Use direct lines and public transit hubs to reduce transfers.',
        ],
        'electric_bus' => [
            'label' => 'Electric Bus',
            'factor' => 0.03,
            'eco' => true,
            'badge' => 'Highly efficient group transport',
            'tip' => 'The best option for collective zero-tailpipe transit.',
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
        'electric_car' => [
            'label' => 'Electric Car',
            'factor' => 0.12,
            'eco' => true,
            'badge' => 'Zero tailpipe emissions personal transport',
            'tip' => 'Opt for this over a standard car if charging infrastructure allows.',
        ],
        'ferry' => [
            'label' => 'Ferries',
            'factor' => 0.10,
            'eco' => true,
            'badge' => 'Scenic water transit',
            'tip' => 'A good alternative to flying if traveling over water.',
        ],
        'cruise' => [
            'label' => 'Cruise',
            'factor' => 0.30,
            'eco' => false,
            'badge' => 'High emissions travel',
            'tip' => 'Opt for shorter trips or environmentally responsible cruise lines.',
        ],
        'electric_boat' => [
            'label' => 'Electric Boat',
            'factor' => 0.02,
            'eco' => true,
            'badge' => 'Zero tailpipe emissions on water',
            'tip' => 'Best for short scenic tours and local water crossing.',
        ],
        'pump_boat' => [
            'label' => 'Pump Boat',
            'factor' => 0.15,
            'eco' => false,
            'badge' => 'Common island hopping transport',
            'tip' => 'Try to share the ride with more passengers to reduce per-capita emissions.',
        ],
        'flight' => [
            'label' => 'Flight',
            'factor' => 0.25,
            'eco' => false,
            'badge' => 'Highest emissions; use only if necessary',
            'tip' => 'If the route is under roughly 800 km, consider rail or coach first.',
        ],
    ];
}

function travel_get_stage_options(): array
{
    return [
        'to_destination' => [
            'label' => 'Going to destination',
            'description' => 'Use this for the fares or route segments that get you to the destination goal.',
        ],
        'inside_destination' => [
            'label' => 'Inside destination',
            'description' => 'Use this for local rides or commute fares after you arrive there.',
        ],
    ];
}

function travel_format_mode(string $mode): string
{
    $transportModes = travel_get_transport_modes();
    return $transportModes[$mode]['label'] ?? ucfirst(str_replace('_', ' ', $mode));
}

function travel_format_stage(string $stage): string
{
    $stageOptions = travel_get_stage_options();
    return $stageOptions[$stage]['label'] ?? ucfirst(str_replace('_', ' ', $stage));
}

function travel_stage_badge_class(string $stage): string
{
    return $stage === 'inside_destination' ? 'stage-local' : 'stage-journey';
}

function travel_clamp(float $value, float $minimum, float $maximum): float
{
    return max($minimum, min($maximum, $value));
}

function travel_recommend_mode(float $distanceKm): array
{
    if ($distanceKm <= 15) {
        return [
            'key' => 'bike',
            'reason' => 'Short distances are usually best handled by bike or other low-impact local transport.',
        ];
    }

    if ($distanceKm <= 120) {
        return [
            'key' => 'bus',
            'reason' => 'For short regional travel, bus travel stays flexible and keeps emissions lower.',
        ];
    }

    if ($distanceKm <= 1200) {
        return [
            'key' => 'train',
            'reason' => 'For this distance, train travel usually gives the lowest carbon footprint.',
        ];
    }

    return [
        'key' => 'train',
        'reason' => 'Long-distance rail is still preferred whenever the route is available.',
    ];
}

function travel_get_next_sequence_order(mysqli $conn, int $destId, string $tripStage, ?int $ignoreTripId = null, ?string $tripDate = null): int
{
    $tripDateValue = travel_get_trip_date_value($tripDate);

    if ($ignoreTripId !== null && $ignoreTripId > 0) {
        $stmt = $conn->prepare(
            'SELECT COALESCE(MAX(i.sequence_order), 0) AS max_order
            FROM itinerary_items i
            INNER JOIN trips t ON t.trip_id = i.trip_id
            WHERE i.dest_id = ? AND i.trip_stage = ? AND COALESCE(t.trip_date, DATE(t.created_at)) = ? AND i.trip_id <> ?'
        );
        $stmt->bind_param('issi', $destId, $tripStage, $tripDateValue, $ignoreTripId);
    } else {
        $stmt = $conn->prepare(
            'SELECT COALESCE(MAX(i.sequence_order), 0) AS max_order
            FROM itinerary_items i
            INNER JOIN trips t ON t.trip_id = i.trip_id
            WHERE i.dest_id = ? AND i.trip_stage = ? AND COALESCE(t.trip_date, DATE(t.created_at)) = ?'
        );
        $stmt->bind_param('iss', $destId, $tripStage, $tripDateValue);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int) ($row['max_order'] ?? 0)) + 1;
}

function travel_build_next_order_help(mysqli $conn, int $destId, ?string $tripDate = null, ?int $ignoreTripId = null): string
{
    $tripDateValue = travel_get_trip_date_value($tripDate);
    $goingThereOrder = travel_get_next_sequence_order($conn, $destId, 'to_destination', $ignoreTripId, $tripDateValue);
    $insideDestinationOrder = travel_get_next_sequence_order($conn, $destId, 'inside_destination', $ignoreTripId, $tripDateValue);

    return sprintf(
        'Next order for %s: going there %d, inside destination %d.',
        travel_format_trip_date($tripDateValue),
        $goingThereOrder,
        $insideDestinationOrder
    );
}

function travel_calculate_eco_rating(mysqli $conn, int $destId): float
{
    $stmt = $conn->prepare('SELECT COUNT(*) as segment_count, AVG(carbon_est) as avg_carbon FROM itinerary_items WHERE dest_id = ?');
    if (!$stmt) {
        return 3.0;
    }
    
    $stmt->bind_param('i', $destId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || (int) $row['segment_count'] === 0) {
        return 3.0;
    }

    $avgCarbon = (float) $row['avg_carbon'];
    $rating = 5.0 - ($avgCarbon / 25.0);
    
    return max(1.0, min(5.0, round($rating, 1)));
}

function travel_calculate_segment_result(array $destination, float $distanceKm, string $transportMode): array
{
    $transportModes = travel_get_transport_modes();
    $recommended = travel_recommend_mode($distanceKm);
    $recommendedMode = $recommended['key'];
    $selectedMode = $transportMode === 'auto' ? $recommendedMode : $transportMode;
    $modeConfig = $transportModes[$selectedMode];
    $destinationRating = isset($destination['eco_rating']) ? (float) $destination['eco_rating'] : 3.0;
    $carbonEstimate = round($distanceKm * $modeConfig['factor'], 2);
    $destinationScore = $destinationRating * 10;
    $transportScore = travel_clamp(60 - ($carbonEstimate * 5), 0, 60);
    $ecoScore = (int) round(travel_clamp($destinationScore + $transportScore, 0, 100));

    return [
        'destination_rating' => $destinationRating,
        'recommended' => $recommended,
        'recommended_mode' => $recommendedMode,
        'selected_mode' => $selectedMode,
        'mode_config' => $modeConfig,
        'carbon_estimate' => $carbonEstimate,
        'eco_score' => $ecoScore,
        'is_eco_friendly' => $modeConfig['eco'] ? 1 : 0,
    ];
}
