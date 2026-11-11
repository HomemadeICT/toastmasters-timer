-- Optional demo meeting. Replace @uid with your users.id before importing.
SET @uid = 1;
INSERT INTO meetings (user_id, name, date, location, theme, tmod, timer_name)
VALUES (@uid, 'Demo Meeting', CURDATE(), 'ZOOM', 'My favourite travel destinations', 'Jordan Lee', 'Sam Timer');
SET @mid = LAST_INSERT_ID();
INSERT INTO event_types (meeting_id, name, min_time, target_time, max_time, is_default) VALUES
(@mid, 'Prepared Speech', 300, 360, 420, TRUE),
(@mid, 'Evaluator', 120, 150, 180, TRUE),
(@mid, 'Table Topic', 60, 90, 120, TRUE);
SET @ps = (SELECT id FROM event_types WHERE meeting_id = @mid AND name = 'Prepared Speech');
SET @ev = (SELECT id FROM event_types WHERE meeting_id = @mid AND name = 'Evaluator');
SET @tt = (SELECT id FROM event_types WHERE meeting_id = @mid AND name = 'Table Topic');
INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES
(@mid, 'Sam Carter', 'Prepared Speech', @ps, 300, 360, 420, 1),
(@mid, 'Ravi Menon', 'Evaluator', @ev, 120, 150, 180, 2),
(@mid, 'Table Topic Speaker 01', 'Table Topic', @tt, 60, 90, 120, 3);
