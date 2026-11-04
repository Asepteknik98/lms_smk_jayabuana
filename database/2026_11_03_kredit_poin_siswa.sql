-- Saldo awal di config/kredit_poin.php; transaksi hanya pengurangan pelanggaran.
CREATE TABLE IF NOT EXISTS kredit_poin_siswa (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    siswa_id int unsigned NOT NULL,
    pencatat_id int unsigned NOT NULL,
    tanggal date NOT NULL,
    perubahan int NOT NULL,
    alasan varchar(500) NOT NULL,
    request_key char(64) NOT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_kps_request (pencatat_id,request_key),
    KEY idx_kps_siswa_tanggal (siswa_id,tanggal,id),
    CONSTRAINT fk_kps_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_kps_pencatat FOREIGN KEY (pencatat_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_kps_hanya_pengurangan CHECK (perubahan BETWEEN -100000 AND -1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
