-- Satu acara dapat ditujukan ke seluruh kelas pada tingkat X, XI, atau XII.
ALTER TABLE kak_acara ADD COLUMN IF NOT EXISTS target_tingkat enum('X','XI','XII') DEFAULT NULL;
