CREATE DATABASE IF NOT EXISTS toast_timer;
USE toast_timer;

CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(255),
    theme VARCHAR(255),
    tmod VARCHAR(255),
    timer_name VARCHAR(255),
    ah_counter VARCHAR(255),
    grammarian VARCHAR(255),
    ge VARCHAR(255),
    rrm VARCHAR(255),
    ttm VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    min_time INT, -- in seconds
    target_time INT, -- in seconds
    max_time INT, -- in seconds
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_per_meeting (meeting_id, name),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS speakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL,
    event_type_id INT,
    min_time INT, -- in seconds
    target_time INT, -- in seconds
    max_time INT, -- in seconds
    final_time VARCHAR(10), -- e.g. "6:14"
    sort_order INT DEFAULT 0,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL
);

-- Seed data for Meeting #111
INSERT INTO meetings (name, date, location, theme, tmod, timer_name, ah_counter, grammarian, ge, rrm, ttm)
VALUES (
    'Meeting # 111', 
    '2026-03-11', 
    'ZOOM', 
    'My favourite travel destinations',
    'Jordan Lee',
    'Theekshana Prabodha',
    'Priya Nair',
    '',
    'TM Chris Patel (Example TMC)',
    'Casey',
    'Morgan Reyes'
);

SET @meeting_id = LAST_INSERT_ID();

-- Default event types for this meeting
INSERT INTO event_types (meeting_id, name, min_time, target_time, max_time, is_default) VALUES
(@meeting_id, 'Prepared Speech', 300, 360, 420, TRUE),
(@meeting_id, 'Evaluator', 120, 150, 180, TRUE),
(@meeting_id, 'Table Topic', 60, 90, 120, TRUE),
(@meeting_id, 'Round Robin', 10, 15, 20, FALSE),
(@meeting_id, 'Q&A Session', 300, 480, 600, FALSE);

SET @ps_event_id = (SELECT id FROM event_types WHERE meeting_id = @meeting_id AND name = 'Prepared Speech');
SET @eval_event_id = (SELECT id FROM event_types WHERE meeting_id = @meeting_id AND name = 'Evaluator');
SET @tt_event_id = (SELECT id FROM event_types WHERE meeting_id = @meeting_id AND name = 'Table Topic');
SET @rr_event_id = (SELECT id FROM event_types WHERE meeting_id = @meeting_id AND name = 'Round Robin');

-- Prepared Speeches
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@meeting_id, 'Sam Carter', 'Prepared Speech', @ps_event_id, 300, 360, 420, 1),
(@meeting_id, 'Taylor Fernandez', 'Prepared Speech', @ps_event_id, 300, 360, 420, 2),
(@meeting_id, 'Riley Shaw', 'Prepared Speech', @ps_event_id, 300, 360, 420, 3),
(@meeting_id, 'Backup Speaker', 'Prepared Speech', @ps_event_id, 300, 360, 420, 4);

-- Evaluators
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@meeting_id, 'Taylor D', 'Evaluator', @eval_event_id, 120, 150, 180, 5),
(@meeting_id, 'Ravi Menon', 'Evaluator', @eval_event_id, 120, 150, 180, 6),
(@meeting_id, 'DTM Dana Allen', 'Evaluator', @eval_event_id, 120, 150, 180, 7);

-- Table Topic Evaluators
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@meeting_id, 'Riley Shaw', 'TTE (O)', @eval_event_id, 120, 150, 180, 8),
(@meeting_id, 'Casey Brooks', 'TTE (E)', @eval_event_id, 120, 150, 180, 9);

-- Table Topics
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@meeting_id, 'Table Topic Speaker 01', 'Table Topic', @tt_event_id, 60, 90, 120, 10),
(@meeting_id, 'Table Topic Speaker 02', 'Table Topic', @tt_event_id, 60, 90, 120, 11),
(@meeting_id, 'Table Topic Speaker 03', 'Table Topic', @tt_event_id, 60, 90, 120, 12),
(@meeting_id, 'Table Topic Speaker 04', 'Table Topic', @tt_event_id, 60, 90, 120, 13);

-- Round Robin Warmup
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@meeting_id, 'Round Robin Warmup', 'Round Robin', @rr_event_id, 10, 15, 20, 14);
