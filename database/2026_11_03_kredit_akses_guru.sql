-- Default: tidak ada guru yang diberi akses sampai dipilih admin.
CREATE TABLE IF NOT EXISTS kredit_akses_guru (
 guru_id int unsigned NOT NULL,
 modul enum('poin','aktivitas') NOT NULL,
 diberikan_oleh int unsigned NOT NULL,
 created_at timestamp NOT NULL DEFAULT current_timestamp(),
 PRIMARY KEY(guru_id,modul),
 CONSTRAINT fk_kredit_akses_guru FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE CASCADE,
 CONSTRAINT fk_kredit_akses_admin FOREIGN KEY(diberikan_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
