<?php
// ============================================================================
// api.php — Complete MySQL Persistence Engine for ALL 8 Tables in AI DJ Studio
// Tables persisted:
//   1. users
//   2. tracks
//   3. track_timeline_events
//   4. dj_lounges
//   5. queue_entries
//   6. soundclash_battles
//   7. chat_messages
//   8. song_requests
// ============================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$pdo = null;
$dbConnected = false;
$dbError = null;

try {
    // Connect to MySQL server first and ensure database exists
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    // Disable foreign key blocks during dynamic inserts so user data ALWAYS saves cleanly into every table
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

// Ensure all 8 tables exist in MySQL
function ensureAllTablesExist(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` VARCHAR(32) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `display_name` VARCHAR(100) NOT NULL,
        `role` ENUM('listener', 'co_dj', 'host', 'admin') NOT NULL DEFAULT 'listener',
        `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tracks` (
        `id` VARCHAR(32) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `artist` VARCHAR(255) NOT NULL,
        `duration_seconds` INT NOT NULL DEFAULT 210,
        `audio_asset_url` VARCHAR(500) DEFAULT NULL,
        `bpm` DECIMAL(5,2) NOT NULL DEFAULT 124.00,
        `camelot_key` VARCHAR(4) NOT NULL DEFAULT '8A',
        `energy` DECIMAL(3,2) NOT NULL DEFAULT 0.85,
        `valence` DECIMAL(3,2) NOT NULL DEFAULT 0.80,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `track_timeline_events` (
        `id` VARCHAR(32) NOT NULL,
        `track_id` VARCHAR(32) NOT NULL,
        `ts_seconds` INT NOT NULL DEFAULT 15,
        `event_type` VARCHAR(32) NOT NULL DEFAULT 'sample_credit',
        `payload` TEXT NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_timeline_track` (`track_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `dj_lounges` (
        `id` VARCHAR(32) NOT NULL,
        `invite_code` VARCHAR(16) NOT NULL,
        `host_user_id` VARCHAR(32) NOT NULL DEFAULT 'u_501',
        `room_name` VARCHAR(255) NOT NULL,
        `vibe_profile` VARCHAR(100) NOT NULL DEFAULT 'High-Energy EDM',
        `current_track_id` VARCHAR(32) DEFAULT 't_2001',
        `status` ENUM('live', 'paused', 'closed') NOT NULL DEFAULT 'live',
        `bpm_tolerance_pct` INT NOT NULL DEFAULT 8,
        `max_participants` INT NOT NULL DEFAULT 50,
        `soundclash_enabled` BOOLEAN NOT NULL DEFAULT TRUE,
        `ai_verbosity` ENUM('minimal', 'normal', 'high') NOT NULL DEFAULT 'normal',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `ended_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `queue_entries` (
        `id` INT AUTO_INCREMENT NOT NULL,
        `lounge_id` VARCHAR(32) NOT NULL DEFAULT 'lg_77120',
        `track_id` VARCHAR(32) NOT NULL,
        `position` INT NOT NULL DEFAULT 1000,
        `vote_count` INT NOT NULL DEFAULT 1,
        `added_by_user_id` VARCHAR(100) DEFAULT 'listener',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_queue_lounge` (`lounge_id`),
        KEY `idx_queue_track` (`track_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `soundclash_battles` (
        `id` VARCHAR(32) NOT NULL,
        `lounge_id` VARCHAR(32) NOT NULL DEFAULT 'lg_77120',
        `track_a_id` VARCHAR(32) NOT NULL,
        `track_b_id` VARCHAR(32) NOT NULL,
        `votes_a` INT NOT NULL DEFAULT 0,
        `votes_b` INT NOT NULL DEFAULT 0,
        `seconds_left` INT NOT NULL DEFAULT 45,
        `status` ENUM('active', 'resolved', 'cancelled') NOT NULL DEFAULT 'active',
        `winner_track_id` VARCHAR(32) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id` VARCHAR(32) NOT NULL,
        `lounge_id` VARCHAR(32) NOT NULL DEFAULT 'lg_77120',
        `user_name` VARCHAR(100) NOT NULL,
        `message_body` TEXT NOT NULL,
        `sentiment_bucket` ENUM('positive', 'neutral', 'negative') NOT NULL DEFAULT 'positive',
        `ts_seconds` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `song_requests` (
        `id` VARCHAR(32) NOT NULL,
        `lounge_id` VARCHAR(32) NOT NULL DEFAULT 'lg_77120',
        `track_id` VARCHAR(32) NOT NULL,
        `requested_by` VARCHAR(100) NOT NULL,
        `dedication_message` VARCHAR(500) NOT NULL,
        `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if ($dbConnected && $pdo) {
    try {
        ensureAllTablesExist($pdo);
    } catch (Exception $e) {
        // Ignore schema warnings
    }
}

function getAllTablesSnapshot(PDO $pdo): array {
    $tables = [
        'users'                 => $pdo->query("SELECT id, email, display_name, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll(),
        'tracks'                => $pdo->query("SELECT * FROM tracks ORDER BY created_at DESC")->fetchAll(),
        'track_timeline_events' => $pdo->query("SELECT * FROM track_timeline_events ORDER BY id DESC")->fetchAll(),
        'dj_lounges'            => $pdo->query("SELECT * FROM dj_lounges ORDER BY created_at DESC")->fetchAll(),
        'queue_entries'         => $pdo->query("SELECT * FROM queue_entries ORDER BY vote_count DESC, position ASC")->fetchAll(),
        'soundclash_battles'    => $pdo->query("SELECT * FROM soundclash_battles ORDER BY created_at DESC")->fetchAll(),
        'chat_messages'         => $pdo->query("SELECT * FROM chat_messages ORDER BY created_at ASC")->fetchAll(),
        'song_requests'         => $pdo->query("SELECT * FROM song_requests ORDER BY created_at DESC")->fetchAll(),
    ];
    $counts = [];
    foreach ($tables as $name => $rows) {
        $counts[$name] = count($rows);
    }
    return ['tables' => $tables, 'counts' => $counts];
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $_GET['action'] ?? $input['action'] ?? 'status';

if (!$dbConnected || !$pdo) {
    echo json_encode([
        'status' => 'fallback',
        'db_connected' => false,
        'message' => 'MySQL offline (' . $dbError . ') — saved in browser storage.'
    ]);
    exit;
}

try {
    switch ($action) {
        // ====================================================================
        // 1. USERS TABLE ACTIONS (signup, login, reset_password, add_user, update_user)
        // ====================================================================
        case 'add_user':
            $uid   = trim($input['id'] ?? ('u_' . rand(1000, 9999)));
            $email = strtolower(trim($input['email'] ?? ('user' . rand(100,999) . '@aidjstudio.com')));
            $name  = trim($input['name'] ?? $input['display_name'] ?? 'Listener');
            $role  = $input['role'] ?? 'listener';
            $stat  = ($input['active'] ?? true) ? 'active' : 'suspended';
            $hash  = password_hash($input['password'] ?? '1234', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (id, email, password_hash, display_name, role, status)
                                   VALUES (?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), role = VALUES(role), status = VALUES(status)");
            $stmt->execute([$uid, $email, $hash, $name, $role, $stat]);

            echo json_encode(['status' => 'success', 'table' => 'users', 'id' => $uid, 'db_connected' => true]);
            break;

        case 'update_user':
            $uid  = trim($input['id'] ?? '');
            $role = $input['role'] ?? null;
            $stat = isset($input['active']) ? ($input['active'] ? 'active' : 'suspended') : ($input['status'] ?? null);

            if ($role !== null && $stat !== null) {
                $stmt = $pdo->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
                $stmt->execute([$role, $stat, $uid]);
            } elseif ($role !== null) {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$role, $uid]);
            } elseif ($stat !== null) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$stat, $uid]);
            }
            echo json_encode(['status' => 'success', 'table' => 'users', 'id' => $uid, 'db_connected' => true]);
            break;

        case 'users':
            $stmt = $pdo->query("SELECT id, email, display_name, role, status, created_at FROM users ORDER BY created_at DESC");
            echo json_encode(['status' => 'success', 'db_connected' => true, 'users' => $stmt->fetchAll()]);
            break;

        // ====================================================================
        // 2 & 3. TRACKS & TRACK_TIMELINE_EVENTS TABLES
        // ====================================================================
        case 'add_track':
            $trackId  = trim($input['trackId'] ?? $input['id'] ?? ('t_' . rand(2100, 9999)));
            $title    = trim($input['title'] ?? 'Custom Track');
            $artist   = trim($input['artist'] ?? 'Independent Artist');
            $duration = intval($input['durationSeconds'] ?? $input['duration_seconds'] ?? 210);
            $audioUrl = trim($input['audioUrl'] ?? $input['audio_asset_url'] ?? '');
            $bpm      = floatval($input['bpm'] ?? ($input['acoustic']['bpm'] ?? 124));
            $key      = strtoupper(trim($input['camelotKey'] ?? ($input['acoustic']['camelotKey'] ?? '8A')));
            $energy   = floatval($input['energy'] ?? ($input['acoustic']['energy'] ?? 0.85));
            $valence  = floatval($input['valence'] ?? ($input['acoustic']['valence'] ?? 0.80));
            $lore     = trim($input['lore'] ?? 'Added live via AI DJ Studio.');

            $stmt = $pdo->prepare("INSERT INTO tracks (id, title, artist, duration_seconds, audio_asset_url, bpm, camelot_key, energy, valence)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE title = VALUES(title), artist = VALUES(artist), bpm = VALUES(bpm), camelot_key = VALUES(camelot_key), energy = VALUES(energy), valence = VALUES(valence)");
            $stmt->execute([$trackId, $title, $artist, $duration, $audioUrl, $bpm, $key, $energy, $valence]);

            // Also insert into track_timeline_events
            $evtId   = trim($input['eventId'] ?? ('evt_' . rand(7000, 99999)));
            $evtType = trim($input['eventType'] ?? 'sample_credit');
            $evtTs   = intval($input['eventTs'] ?? 15);
            $stmtEvt = $pdo->prepare("INSERT INTO track_timeline_events (id, track_id, ts_seconds, event_type, payload)
                                      VALUES (?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE payload = VALUES(payload)");
            $stmtEvt->execute([$evtId, $trackId, $evtTs, $evtType, $lore]);

            // If alsoQueue is true, insert into queue_entries
            if (!empty($input['alsoQueue'])) {
                $loungeId = trim($input['loungeId'] ?? 'lg_77120');
                $votes    = intval($input['votes'] ?? 12);
                $addedBy  = trim($input['addedBy'] ?? 'u_501');
                $pos      = intval($input['position'] ?? 1000);
                $qStmt = $pdo->prepare("INSERT INTO queue_entries (lounge_id, track_id, position, vote_count, added_by_user_id) VALUES (?, ?, ?, ?, ?)");
                $qStmt->execute([$loungeId, $trackId, $pos, $votes, $addedBy]);
            }

            echo json_encode([
                'status' => 'success',
                'tables_updated' => ['tracks', 'track_timeline_events'],
                'trackId' => $trackId,
                'eventId' => $evtId,
                'db_connected' => true
            ]);
            break;

        // ====================================================================
        // 4. DJ_LOUNGES TABLE (create_room, update_room, close_room)
        // ====================================================================
        case 'lounges':
        case 'create_room':
            $loungeId   = trim($input['id'] ?? $input['loungeId'] ?? ('lg_' . rand(10000, 99999)));
            $inviteCode = strtoupper(trim($input['code'] ?? $input['inviteCode'] ?? ('DJ-' . rand(1000, 9999))));
            $hostUser   = trim($input['hostUserId'] ?? $input['host'] ?? 'u_501');
            $roomName   = trim($input['name'] ?? $input['room_name'] ?? 'Live DJ Lounge');
            $vibe       = trim($input['vibe'] ?? $input['vibeProfile'] ?? 'High-Energy EDM');
            $currTrack  = trim($input['currentTrackId'] ?? 't_2001');
            $bpmTol     = intval($input['bpmTolerancePct'] ?? 8);
            $maxCap     = intval($input['maxGuests'] ?? $input['maxParticipants'] ?? 50);
            $scEnabled  = isset($input['soundclashEnabled']) ? ($input['soundclashEnabled'] ? 1 : 0) : 1;
            $verbosity  = $input['aiVerbosity'] ?? 'normal';
            if (!in_array($verbosity, ['minimal', 'normal', 'high'])) $verbosity = 'normal';

            $stmt = $pdo->prepare("INSERT INTO dj_lounges (id, invite_code, host_user_id, room_name, vibe_profile, current_track_id, status, bpm_tolerance_pct, max_participants, soundclash_enabled, ai_verbosity)
                                   VALUES (?, ?, ?, ?, ?, ?, 'live', ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE room_name = VALUES(room_name), vibe_profile = VALUES(vibe_profile), bpm_tolerance_pct = VALUES(bpm_tolerance_pct), max_participants = VALUES(max_participants), ai_verbosity = VALUES(ai_verbosity), status = 'live'");
            $stmt->execute([$loungeId, $inviteCode, $hostUser, $roomName, $vibe, $currTrack, $bpmTol, $maxCap, $scEnabled, $verbosity]);

            echo json_encode([
                'status' => 'success',
                'table' => 'dj_lounges',
                'loungeId' => $loungeId,
                'inviteCode' => $inviteCode,
                'db_connected' => true
            ]);
            break;

        case 'update_room':
            $loungeId  = trim($input['loungeId'] ?? 'lg_77120');
            $vibe      = trim($input['vibeProfile'] ?? 'High-Energy EDM');
            $bpmTol    = intval($input['bpmTolerancePct'] ?? 8);
            $maxCap    = intval($input['maxParticipants'] ?? 50);
            $verbosity = $input['aiVerbosity'] ?? 'normal';
            $currTrack = $input['currentTrackId'] ?? null;

            if ($currTrack) {
                $stmt = $pdo->prepare("UPDATE dj_lounges SET vibe_profile = ?, bpm_tolerance_pct = ?, max_participants = ?, ai_verbosity = ?, current_track_id = ? WHERE id = ?");
                $stmt->execute([$vibe, $bpmTol, $maxCap, $verbosity, $currTrack, $loungeId]);
            } else {
                $stmt = $pdo->prepare("UPDATE dj_lounges SET vibe_profile = ?, bpm_tolerance_pct = ?, max_participants = ?, ai_verbosity = ? WHERE id = ?");
                $stmt->execute([$vibe, $bpmTol, $maxCap, $verbosity, $loungeId]);
            }
            echo json_encode(['status' => 'success', 'table' => 'dj_lounges', 'loungeId' => $loungeId, 'db_connected' => true]);
            break;

        case 'close_room':
            $loungeId = trim($input['loungeId'] ?? $input['id'] ?? '');
            $stmt = $pdo->prepare("UPDATE dj_lounges SET status = 'closed', ended_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$loungeId]);
            echo json_encode(['status' => 'success', 'table' => 'dj_lounges', 'loungeId' => $loungeId, 'db_connected' => true]);
            break;

        // ====================================================================
        // 5. QUEUE_ENTRIES TABLE (add_queue, vote_queue, remove_queue, sync_queue)
        // ====================================================================
        case 'add_queue':
        case 'vote_queue':
            $loungeId = trim($input['loungeId'] ?? 'lg_77120');
            $trackId  = trim($input['trackId'] ?? 't_2001');
            $votes    = intval($input['votes'] ?? 1);
            $position = intval($input['position'] ?? 1000);
            $addedBy  = trim($input['addedBy'] ?? 'u_501');

            // Check if this track is already in queue_entries for this lounge
            $chk = $pdo->prepare("SELECT id, vote_count FROM queue_entries WHERE lounge_id = ? AND track_id = ? LIMIT 1");
            $chk->execute([$loungeId, $trackId]);
            $existing = $chk->fetch();

            if ($existing) {
                $newVotes = isset($input['votes']) ? $votes : (intval($existing['vote_count']) + 1);
                $upd = $pdo->prepare("UPDATE queue_entries SET vote_count = ?, position = ? WHERE id = ?");
                $upd->execute([$newVotes, $position, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO queue_entries (lounge_id, track_id, position, vote_count, added_by_user_id) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$loungeId, $trackId, $position, $votes, $addedBy]);
            }

            // If full queue array is also sent, update positions atomically
            if (!empty($input['queue']) && is_array($input['queue'])) {
                foreach ($input['queue'] as $qItem) {
                    $qt = $qItem['trackId'] ?? '';
                    $qp = intval($qItem['position'] ?? 1000);
                    $qv = intval($qItem['votes'] ?? 1);
                    if ($qt) {
                        $uStmt = $pdo->prepare("UPDATE queue_entries SET position = ?, vote_count = ? WHERE lounge_id = ? AND track_id = ?");
                        $uStmt->execute([$qp, $qv, $loungeId, $qt]);
                    }
                }
            }

            echo json_encode(['status' => 'success', 'table' => 'queue_entries', 'trackId' => $trackId, 'db_connected' => true]);
            break;

        case 'remove_queue':
            $loungeId = trim($input['loungeId'] ?? 'lg_77120');
            $trackId  = trim($input['trackId'] ?? '');
            $del = $pdo->prepare("DELETE FROM queue_entries WHERE lounge_id = ? AND track_id = ?");
            $del->execute([$loungeId, $trackId]);
            echo json_encode(['status' => 'success', 'table' => 'queue_entries', 'removedTrackId' => $trackId, 'db_connected' => true]);
            break;

        case 'sync_queue':
            $loungeId = trim($input['loungeId'] ?? 'lg_77120');
            $queue    = $input['queue'] ?? [];
            if (is_array($queue)) {
                foreach ($queue as $idx => $qItem) {
                    $tid   = trim($qItem['trackId'] ?? '');
                    $pos   = intval($qItem['position'] ?? (($idx + 1) * 1000));
                    $votes = intval($qItem['votes'] ?? 1);
                    $by    = trim($qItem['addedBy'] ?? 'u_501');
                    if (!$tid) continue;

                    $chk = $pdo->prepare("SELECT id FROM queue_entries WHERE lounge_id = ? AND track_id = ? LIMIT 1");
                    $chk->execute([$loungeId, $tid]);
                    $found = $chk->fetch();
                    if ($found) {
                        $u = $pdo->prepare("UPDATE queue_entries SET position = ?, vote_count = ? WHERE id = ?");
                        $u->execute([$pos, $votes, $found['id']]);
                    } else {
                        $i = $pdo->prepare("INSERT INTO queue_entries (lounge_id, track_id, position, vote_count, added_by_user_id) VALUES (?, ?, ?, ?, ?)");
                        $i->execute([$loungeId, $tid, $pos, $votes, $by]);
                    }
                }
            }
            echo json_encode(['status' => 'success', 'table' => 'queue_entries', 'db_connected' => true]);
            break;

        // ====================================================================
        // 6. SOUNDCLASH_BATTLES TABLE (vote_soundclash, add_challenger, resolve_soundclash)
        // ====================================================================
        case 'vote_soundclash':
        case 'add_challenger':
        case 'resolve_soundclash':
            $battleId   = trim($input['battleId'] ?? 'sc_902');
            $loungeId   = trim($input['loungeId'] ?? 'lg_77120');
            $trackA     = trim($input['trackAId'] ?? 't_2002');
            $trackB     = trim($input['trackBId'] ?? 't_2004');
            $votesA     = intval($input['votesA'] ?? 24);
            $votesB     = intval($input['votesB'] ?? 21);
            $secLeft    = intval($input['secondsLeft'] ?? 42);
            $status     = $input['status'] ?? ($action === 'resolve_soundclash' ? 'resolved' : 'active');
            $winnerId   = $input['winnerTrackId'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO soundclash_battles (id, lounge_id, track_a_id, track_b_id, votes_a, votes_b, seconds_left, status, winner_track_id)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE track_a_id = VALUES(track_a_id), track_b_id = VALUES(track_b_id), votes_a = VALUES(votes_a), votes_b = VALUES(votes_b), seconds_left = VALUES(seconds_left), status = VALUES(status), winner_track_id = VALUES(winner_track_id)");
            $stmt->execute([$battleId, $loungeId, $trackA, $trackB, $votesA, $votesB, $secLeft, $status, $winnerId]);

            echo json_encode(['status' => 'success', 'table' => 'soundclash_battles', 'battleId' => $battleId, 'db_connected' => true]);
            break;

        // ====================================================================
        // 7. CHAT_MESSAGES TABLE (add_chat)
        // ====================================================================
        case 'add_chat':
            $msgId     = trim($input['id'] ?? ('m_' . uniqid()));
            $loungeId  = trim($input['loungeId'] ?? 'lg_77120');
            $userName  = trim($input['user'] ?? $input['user_name'] ?? 'Listener');
            $body      = trim($input['body'] ?? $input['message_body'] ?? '');
            $sentiment = $input['sentiment'] ?? $input['sentiment_bucket'] ?? 'positive';
            if (!in_array($sentiment, ['positive', 'neutral', 'negative'])) $sentiment = 'positive';
            $tsSec     = intval($input['tsSeconds'] ?? 0);

            if ($body !== '') {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (id, lounge_id, user_name, message_body, sentiment_bucket, ts_seconds)
                                       VALUES (?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE message_body = VALUES(message_body), sentiment_bucket = VALUES(sentiment_bucket)");
                $stmt->execute([$msgId, $loungeId, $userName, $body, $sentiment, $tsSec]);
            }
            echo json_encode(['status' => 'success', 'table' => 'chat_messages', 'id' => $msgId, 'db_connected' => true]);
            break;

        // ====================================================================
        // 8. SONG_REQUESTS TABLE (add_song_request, update_song_request)
        // ====================================================================
        case 'add_song_request':
            $reqId      = trim($input['id'] ?? ('req_' . uniqid()));
            $loungeId   = trim($input['loungeId'] ?? 'lg_77120');
            $trackId    = trim($input['trackId'] ?? 't_2002');
            $reqBy      = trim($input['user'] ?? $input['requested_by'] ?? 'Listener');
            $dedication = trim($input['dedication'] ?? $input['dedication_message'] ?? 'Play this next please!');
            $status     = $input['status'] ?? 'pending';

            $stmt = $pdo->prepare("INSERT INTO song_requests (id, lounge_id, track_id, requested_by, dedication_message, status)
                                   VALUES (?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE status = VALUES(status), dedication_message = VALUES(dedication_message)");
            $stmt->execute([$reqId, $loungeId, $trackId, $reqBy, $dedication, $status]);

            echo json_encode(['status' => 'success', 'table' => 'song_requests', 'id' => $reqId, 'db_connected' => true]);
            break;

        case 'update_song_request':
            $reqId  = trim($input['id'] ?? '');
            $status = $input['status'] ?? 'approved';
            if (!in_array($status, ['pending', 'approved', 'rejected'])) $status = 'approved';

            $stmt = $pdo->prepare("UPDATE song_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $reqId]);

            echo json_encode(['status' => 'success', 'table' => 'song_requests', 'id' => $reqId, 'status_value' => $status, 'db_connected' => true]);
            break;

        // ====================================================================
        // 9. SYNC FULL STATE ACROSS ALL 8 TABLES & RETURN SNAPSHOT
        // ====================================================================
        case 'sync_full_state':
            // 1. Users
            if (!empty($input['users']) && is_array($input['users'])) {
                $uStmt = $pdo->prepare("INSERT INTO users (id, email, password_hash, display_name, role, status)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), role = VALUES(role), status = VALUES(status)");
                foreach ($input['users'] as $u) {
                    $uid = $u['id'] ?? ('u_' . rand(1000, 9999));
                    $em  = strtolower(trim($u['email'] ?? ($uid . '@aidjstudio.com')));
                    $nm  = $u['name'] ?? $u['display_name'] ?? 'User';
                    $rl  = $u['role'] ?? 'listener';
                    $st  = (!isset($u['active']) || $u['active']) ? 'active' : 'suspended';
                    $pw  = password_hash('1234', PASSWORD_DEFAULT);
                    $uStmt->execute([$uid, $em, $pw, $nm, $rl, $st]);
                }
            }

            // 2 & 3. Tracks & Timeline Events
            if (!empty($input['tracks']) && is_array($input['tracks'])) {
                $tStmt = $pdo->prepare("INSERT INTO tracks (id, title, artist, duration_seconds, audio_asset_url, bpm, camelot_key, energy, valence)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE title = VALUES(title), artist = VALUES(artist), bpm = VALUES(bpm), camelot_key = VALUES(camelot_key), energy = VALUES(energy), valence = VALUES(valence)");
                $eStmt = $pdo->prepare("INSERT INTO track_timeline_events (id, track_id, ts_seconds, event_type, payload)
                                        VALUES (?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE payload = VALUES(payload)");
                foreach ($input['tracks'] as $t) {
                    $tid = $t['trackId'] ?? $t['id'] ?? ('t_' . rand(2000, 9999));
                    $ttl = $t['title'] ?? 'Track';
                    $art = $t['artist'] ?? 'Artist';
                    $dur = intval($t['durationSeconds'] ?? 210);
                    $url = $t['audioUrl'] ?? '';
                    $bpm = floatval($t['acoustic']['bpm'] ?? 124);
                    $key = $t['acoustic']['camelotKey'] ?? '8A';
                    $nrg = floatval($t['acoustic']['energy'] ?? 0.85);
                    $val = floatval($t['acoustic']['valence'] ?? 0.80);
                    $tStmt->execute([$tid, $ttl, $art, $dur, $url, $bpm, $key, $nrg, $val]);

                    if (!empty($t['timeline']) && is_array($t['timeline'])) {
                        foreach ($t['timeline'] as $ev) {
                            $eid = $ev['id'] ?? ('evt_' . rand(1000, 99999));
                            $ets = intval($ev['ts'] ?? 15);
                            $etp = $ev['type'] ?? 'trivia';
                            $txt = $ev['text'] ?? '';
                            if ($txt) $eStmt->execute([$eid, $tid, $ets, $etp, $txt]);
                        }
                    }
                }
            }

            // 4. DJ Lounges
            if (!empty($input['rooms']) && is_array($input['rooms'])) {
                $rStmt = $pdo->prepare("INSERT INTO dj_lounges (id, invite_code, host_user_id, room_name, vibe_profile, current_track_id, status, bpm_tolerance_pct, max_participants, soundclash_enabled, ai_verbosity)
                                        VALUES (?, ?, 'u_501', ?, ?, 't_2001', 'live', 8, ?, 1, 'normal')
                                        ON DUPLICATE KEY UPDATE room_name = VALUES(room_name), vibe_profile = VALUES(vibe_profile), max_participants = VALUES(max_participants)");
                foreach ($input['rooms'] as $r) {
                    $rid  = $r['id'] ?? ('lg_' . rand(10000, 99999));
                    $code = $r['code'] ?? ('DJ-' . rand(1000, 9999));
                    $rnm  = $r['name'] ?? 'DJ Lounge';
                    $vb   = $r['vibe'] ?? 'High-Energy EDM';
                    $maxG = intval($r['maxGuests'] ?? 50);
                    $rStmt->execute([$rid, $code, $rnm, $vb, $maxG]);
                }
            }

            // 5. Queue Entries
            $activeLounge = $input['loungeId'] ?? 'lg_77120';
            if (!empty($input['queue']) && is_array($input['queue'])) {
                foreach ($input['queue'] as $idx => $q) {
                    $qtid = $q['trackId'] ?? '';
                    $qpos = intval($q['position'] ?? (($idx + 1) * 1000));
                    $qvot = intval($q['votes'] ?? 1);
                    $qby  = $q['addedBy'] ?? 'u_501';
                    if (!$qtid) continue;
                    $chk = $pdo->prepare("SELECT id FROM queue_entries WHERE lounge_id = ? AND track_id = ? LIMIT 1");
                    $chk->execute([$activeLounge, $qtid]);
                    $f = $chk->fetch();
                    if ($f) {
                        $pdo->prepare("UPDATE queue_entries SET position = ?, vote_count = ? WHERE id = ?")->execute([$qpos, $qvot, $f['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO queue_entries (lounge_id, track_id, position, vote_count, added_by_user_id) VALUES (?, ?, ?, ?, ?)")->execute([$activeLounge, $qtid, $qpos, $qvot, $qby]);
                    }
                }
            }

            // 6. Soundclash Battles
            if (!empty($input['soundclash']) && is_array($input['soundclash'])) {
                $sc = $input['soundclash'];
                $bid = $sc['battleId'] ?? 'sc_902';
                $cands = $sc['candidates'] ?? [];
                $ta = $cands[0]['trackId'] ?? 't_2002';
                $tb = $cands[1]['trackId'] ?? 't_2004';
                $va = intval($cands[0]['votes'] ?? 24);
                $vb = intval($cands[1]['votes'] ?? 21);
                $sl = intval($sc['secondsLeft'] ?? 42);
                $st = !empty($sc['active']) ? 'active' : 'resolved';
                $pdo->prepare("INSERT INTO soundclash_battles (id, lounge_id, track_a_id, track_b_id, votes_a, votes_b, seconds_left, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE votes_a = VALUES(votes_a), votes_b = VALUES(votes_b), seconds_left = VALUES(seconds_left), status = VALUES(status)")
                    ->execute([$bid, $activeLounge, $ta, $tb, $va, $vb, $sl, $st]);
            }

            // 7. Chat Messages
            if (!empty($input['chatMessages']) && is_array($input['chatMessages'])) {
                $cStmt = $pdo->prepare("INSERT INTO chat_messages (id, lounge_id, user_name, message_body, sentiment_bucket, ts_seconds)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE message_body = VALUES(message_body)");
                foreach ($input['chatMessages'] as $m) {
                    $mid  = $m['id'] ?? ('m_' . uniqid());
                    $musr = $m['user'] ?? 'Listener';
                    $mbod = $m['body'] ?? '';
                    $msnt = in_array($m['sentiment'] ?? '', ['positive', 'neutral', 'negative']) ? $m['sentiment'] : 'positive';
                    if ($mbod !== '') $cStmt->execute([$mid, $activeLounge, $musr, $mbod, $msnt, 15]);
                }
            }

            // 8. Song Requests
            if (!empty($input['songRequests']) && is_array($input['songRequests'])) {
                $srStmt = $pdo->prepare("INSERT INTO song_requests (id, lounge_id, track_id, requested_by, dedication_message, status)
                                         VALUES (?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE status = VALUES(status), dedication_message = VALUES(dedication_message)");
                foreach ($input['songRequests'] as $sr) {
                    $srid = $sr['id'] ?? ('req_' . uniqid());
                    $stId = $sr['trackId'] ?? 't_2002';
                    $susr = $sr['user'] ?? 'Listener';
                    $sded = $sr['dedication'] ?? 'Play this next!';
                    $sst  = in_array($sr['status'] ?? '', ['pending', 'approved', 'rejected']) ? $sr['status'] : 'pending';
                    $srStmt->execute([$srid, $activeLounge, $stId, $susr, $sded, $sst]);
                }
            }

            $snapshot = getAllTablesSnapshot($pdo);
            echo json_encode([
                'status' => 'success',
                'db_connected' => true,
                'message' => 'All 8 MySQL tables synchronized!',
                'counts' => $snapshot['counts'],
                'tables' => $snapshot['tables']
            ]);
            break;

        case 'get_all_tables':
        default:
            $snapshot = getAllTablesSnapshot($pdo);
            echo json_encode([
                'status' => 'success',
                'db_connected' => true,
                'database' => $db,
                'counts' => $snapshot['counts'],
                'tables' => $snapshot['tables']
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'db_connected' => true,
        'message' => 'SQL Error: ' . $e->getMessage()
    ]);
}
