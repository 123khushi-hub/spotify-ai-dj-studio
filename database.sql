-- ============================================================================
-- AI DJ STUDIO — COMPLETE RELATIONAL DATABASE SCHEMA & SEED DATA (MySQL / MariaDB)
-- Compatible with: phpMyAdmin, XAMPP, WampServer, Docker MySQL 5.7+ / 8.0+ / MariaDB
-- ============================================================================

-- Note: On Alwaysdata / cPanel, select your database in phpMyAdmin first, then import this file.
-- (For local XAMPP, create or select `ai_dj_studio` in phpMyAdmin before importing)

-- Disable Foreign Key checks for clean drop & reload
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `song_requests`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `soundclash_battles`;
DROP TABLE IF EXISTS `queue_entries`;
DROP TABLE IF EXISTS `dj_lounges`;
DROP TABLE IF EXISTS `track_timeline_events`;
DROP TABLE IF EXISTS `tracks`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. USERS TABLE (RBAC: Listener, Co-DJ, Host, Admin)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` VARCHAR(32) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `role` ENUM('listener', 'co_dj', 'host', 'admin') NOT NULL DEFAULT 'listener',
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. TRACKS TABLE (Acoustic Profiles: BPM, Camelot Key, Energy, Valence)
-- ----------------------------------------------------------------------------
CREATE TABLE `tracks` (
    `id` VARCHAR(32) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `artist` VARCHAR(255) NOT NULL,
    `duration_seconds` INT NOT NULL,
    `audio_asset_url` VARCHAR(500) DEFAULT NULL,
    `bpm` DECIMAL(5,2) NOT NULL,
    `camelot_key` VARCHAR(4) NOT NULL,
    `energy` DECIMAL(3,2) NOT NULL,
    `valence` DECIMAL(3,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. TRACK TIMELINE EVENTS (RAG Grounding: Sample Credits, Synth Lore, Trivia)
-- ----------------------------------------------------------------------------
CREATE TABLE `track_timeline_events` (
    `id` VARCHAR(32) NOT NULL,
    `track_id` VARCHAR(32) NOT NULL,
    `ts_seconds` INT NOT NULL,
    `event_type` VARCHAR(32) NOT NULL,
    `payload` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_timeline_track` (`track_id`),
    CONSTRAINT `fk_timeline_track` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. DJ LOUNGES TABLE (Live Music Rooms, Invite Codes & DJ Transition Rules)
-- ----------------------------------------------------------------------------
CREATE TABLE `dj_lounges` (
    `id` VARCHAR(32) NOT NULL,
    `invite_code` VARCHAR(16) NOT NULL,
    `host_user_id` VARCHAR(32) NOT NULL,
    `room_name` VARCHAR(255) NOT NULL,
    `vibe_profile` VARCHAR(100) NOT NULL,
    `current_track_id` VARCHAR(32) DEFAULT NULL,
    `status` ENUM('live', 'paused', 'closed') NOT NULL DEFAULT 'live',
    `bpm_tolerance_pct` INT NOT NULL DEFAULT 8,
    `max_participants` INT NOT NULL DEFAULT 50,
    `soundclash_enabled` BOOLEAN NOT NULL DEFAULT TRUE,
    `ai_verbosity` ENUM('minimal', 'normal', 'high') NOT NULL DEFAULT 'normal',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lounge_code` (`invite_code`),
    KEY `idx_lounge_host` (`host_user_id`),
    CONSTRAINT `fk_lounge_host` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_lounge_track` FOREIGN KEY (`current_track_id`) REFERENCES `tracks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. QUEUE ENTRIES (Collaborative Live Queue with Sparse Integer Positions)
-- ----------------------------------------------------------------------------
CREATE TABLE `queue_entries` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `lounge_id` VARCHAR(32) NOT NULL,
    `track_id` VARCHAR(32) NOT NULL,
    `position` INT NOT NULL COMMENT 'Sparse integer (1000, 2000, 3000) for fast reorders',
    `vote_count` INT NOT NULL DEFAULT 1,
    `added_by_user_id` VARCHAR(32) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue_lounge` (`lounge_id`),
    KEY `idx_queue_track` (`track_id`),
    CONSTRAINT `fk_queue_lounge` FOREIGN KEY (`lounge_id`) REFERENCES `dj_lounges` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_track` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_user` FOREIGN KEY (`added_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. SOUNDCLASH BATTLES TABLE (Live Song Battles & Crowd Voting)
-- ----------------------------------------------------------------------------
CREATE TABLE `soundclash_battles` (
    `id` VARCHAR(32) NOT NULL,
    `lounge_id` VARCHAR(32) NOT NULL,
    `track_a_id` VARCHAR(32) NOT NULL,
    `track_b_id` VARCHAR(32) NOT NULL,
    `votes_a` INT NOT NULL DEFAULT 0,
    `votes_b` INT NOT NULL DEFAULT 0,
    `seconds_left` INT NOT NULL DEFAULT 45,
    `status` ENUM('active', 'resolved', 'cancelled') NOT NULL DEFAULT 'active',
    `winner_track_id` VARCHAR(32) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_soundclash_lounge` (`lounge_id`),
    CONSTRAINT `fk_soundclash_lounge` FOREIGN KEY (`lounge_id`) REFERENCES `dj_lounges` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_soundclash_track_a` FOREIGN KEY (`track_a_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_soundclash_track_b` FOREIGN KEY (`track_b_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_soundclash_winner` FOREIGN KEY (`winner_track_id`) REFERENCES `tracks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. CHAT MESSAGES TABLE (Live Room Chat with Real-Time Sentiment Scoring)
-- ----------------------------------------------------------------------------
CREATE TABLE `chat_messages` (
    `id` VARCHAR(32) NOT NULL,
    `lounge_id` VARCHAR(32) NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `message_body` TEXT NOT NULL,
    `sentiment_bucket` ENUM('positive', 'neutral', 'negative') NOT NULL DEFAULT 'positive',
    `ts_seconds` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_lounge` (`lounge_id`),
    CONSTRAINT `fk_chat_lounge` FOREIGN KEY (`lounge_id`) REFERENCES `dj_lounges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. SONG REQUESTS TABLE (Listener Dedications & Host Approvals)
-- ----------------------------------------------------------------------------
CREATE TABLE `song_requests` (
    `id` VARCHAR(32) NOT NULL,
    `lounge_id` VARCHAR(32) NOT NULL,
    `track_id` VARCHAR(32) NOT NULL,
    `requested_by` VARCHAR(100) NOT NULL,
    `dedication_message` VARCHAR(500) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_requests_lounge` (`lounge_id`),
    KEY `idx_requests_track` (`track_id`),
    CONSTRAINT `fk_requests_lounge` FOREIGN KEY (`lounge_id`) REFERENCES `dj_lounges` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_requests_track` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- SEED DATA (Ready-to-use Sample Records for Instant Testing & Demo)
-- ============================================================================

-- 1. Insert Demo Users
INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `role`, `status`) VALUES
('u_501', 'host@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aarav Mehta', 'host', 'active'),
('u_502', 'maya@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maya Lin', 'co_dj', 'active'),
('u_503', 'priya@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Priya Sharma', 'listener', 'active'),
('u_504', 'alex@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alex Rivera', 'listener', 'active'),
('u_505', 'admin@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vikram Patel', 'admin', 'active'),
('u_506', 'user@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guest Listener', 'listener', 'active'),
('u_507', 'listener@aidjstudio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Priya Sharma', 'listener', 'active');

-- 2. Insert Song Library (Acoustic Keys & Streaming URLs)
INSERT INTO `tracks` (`id`, `title`, `artist`, `duration_seconds`, `audio_asset_url`, `bpm`, `camelot_key`, `energy`, `valence`) VALUES
('t_2001', 'Kala Chashma', 'Amar Arshi, Badshah, Neha Kakkar', 187, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Afghan%20Jalebi%20(Ya%20Baba)%20FULL%20VIDEO%20Song%20Phantom%20Saif%20Ali%20Khan%20Katrina%20Kaif%20T-Series.mp3', 138.00, '11B', 0.94, 0.88),
('t_2002', 'Ghungroo', 'Arijit Singh, Shilpa Rao, Vishal-Shekhar', 204, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Nashe%20Si%20Chadh%20Gayi.mp3', 118.00, '9A', 0.85, 0.78),
('t_2003', 'Apna Bana Le', 'Arijit Singh, Sachin-Jigar', 215, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Dil_Diyan_Gallan-.mp3', 96.00, '8A', 0.65, 0.60),
('t_2004', 'Kar Gayi Chull', 'Badshah, Fazilpuria, Neha Kakkar', 185, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Chalti%20Hai%20Kya%209%20Se%2012%20-%20Judwaa%202.mp3', 126.00, '8A', 0.90, 0.85),
('t_2005', 'Kesariya', 'Arijit Singh, Pritam', 218, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Channa%20Mereya.mp3', 104.00, '8A', 0.72, 0.68),
('t_2006', 'Badtameez Dil', 'Benny Dayal, Pritam', 192, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Lamberghini%20(Full%20Video)%20The%20Doorbeen%20Feat%20Ragini%20Latest%20Punjabi%20Song%202018%20Speed%20Records.mp3', 152.00, '8B', 0.96, 0.92),
('t_2007', 'Chaiyya Chaiyya', 'Sukhwinder Singh, A.R. Rahman', 234, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/Tune_Maari_Entriyaan(MyMp3Song).mp3', 116.00, '9A', 0.88, 0.80),
('t_2008', 'Tauba Tauba', 'Karan Aujla', 180, 'https://raw.githubusercontent.com/pushkarlaulkar/Music/main/ANIMAL%20ARJAN%20VAILLY.mp3', 110.00, '7A', 0.92, 0.86);

-- 3. Insert Timeline & Trivia Lore
INSERT INTO `track_timeline_events` (`id`, `track_id`, `ts_seconds`, `event_type`, `payload`) VALUES
('evt_5541', 't_2001', 12, 'sample_credit', 'Classic Punjabi folk melody by Amrik Singh Shera remixed by Badshah with 808 sub-bass.'),
('evt_5542', 't_2001', 85, 'trivia', 'Over 1 Billion global views; recorded with live dholak percussion in Mumbai.'),
('evt_6101', 't_2002', 18, 'sample_credit', 'Nu-disco slap bass and chic rhythm guitar composed by Vishal-Shekhar.'),
('evt_6201', 't_2003', 24, 'sample_credit', 'Soulful acoustic guitar arpeggios and sarangi lines composed by Sachin-Jigar.'),
('evt_6301', 't_2004', 15, 'sample_credit', 'Haryanvi-Bollywood party banger adapted by Badshah with club house synths.'),
('evt_6401', 't_2005', 30, 'sample_credit', 'Rich acoustic strings, dholak, and harmonium melody arranged by Pritam.'),
('evt_6501', 't_2006', 10, 'trivia', 'Fast swing-rock tempo (152 BPM) featuring Benny Dayal vocal scatting and 8-piece brass horns.');

-- 4. Insert Default DJ Lounges
INSERT INTO `dj_lounges` (`id`, `invite_code`, `host_user_id`, `room_name`, `vibe_profile`, `current_track_id`, `status`, `bpm_tolerance_pct`, `max_participants`, `soundclash_enabled`, `ai_verbosity`) VALUES
('lg_77120', 'SP-9X2L', 'u_501', 'Bollywood Dhamaka Lounge', 'High-Energy Bollywood Party', 't_2001', 'live', 8, 40, TRUE, 'normal'),
('lg_88310', 'LF-4K8P', 'u_502', 'Arijit Singh Acoustic Cafe', 'Soulful & Romantic', 't_2003', 'live', 6, 50, TRUE, 'high'),
('lg_99204', 'ND-7M2Q', 'u_501', 'Punjabi Beat Drop Club', 'Peak-Hour Punjabi Beats', 't_2004', 'live', 10, 30, TRUE, 'normal');

-- 5. Insert Live Collaborative Queue
INSERT INTO `queue_entries` (`lounge_id`, `track_id`, `position`, `vote_count`, `added_by_user_id`) VALUES
('lg_77120', 't_2004', 1000, 18, 'u_502'),
('lg_77120', 't_2002', 2000, 14, 'u_504'),
('lg_77120', 't_2005', 3000, 10, 'u_503'),
('lg_77120', 't_2003', 4000, 7, 'u_504');

-- 6. Insert Active Soundclash Battle
INSERT INTO `soundclash_battles` (`id`, `lounge_id`, `track_a_id`, `track_b_id`, `votes_a`, `votes_b`, `seconds_left`, `status`, `winner_track_id`) VALUES
('sc_902', 'lg_77120', 't_2002', 't_2004', 24, 21, 42, 'active', NULL);

-- 7. Insert Sample Chat Feed (Sentiment Scored)
INSERT INTO `chat_messages` (`id`, `lounge_id`, `user_name`, `message_body`, `sentiment_bucket`, `ts_seconds`) VALUES
('m_1', 'lg_77120', 'Maya Lin', 'Kala Chashma ka beat drop pure fire hai boss! 🔥', 'positive', 12),
('m_2', 'lg_77120', 'Alex Rivera', 'Arre agla gaana Ghungroo lagao, dono ka beat match ho raha hai!', 'positive', 85),
('m_3', 'lg_77120', 'Priya Sharma', 'Soundclash Battle me vote kar diya! Kar Gayi Chull jeetega 💃', 'positive', 140);

-- 8. Insert Listener Song Requests & Dedications
INSERT INTO `song_requests` (`id`, `lounge_id`, `track_id`, `requested_by`, `dedication_message`, `status`) VALUES
('req_101', 'lg_77120', 't_2002', 'Rohan_K', 'Dedicated to our College Fest team! Ghungroo bajao! 🎉', 'pending'),
('req_102', 'lg_77120', 't_2004', 'Sneha_M', 'Kar Gayi Chull next please! Best house party groove 🔥', 'pending');
