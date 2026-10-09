ALTER TABLE providers
    ADD COLUMN opening_time TIME NULL AFTER opening_hours,
    ADD COLUMN closing_time TIME NULL AFTER opening_time,
    ADD COLUMN closed_days VARCHAR(100) NOT NULL DEFAULT '' AFTER closing_time;
