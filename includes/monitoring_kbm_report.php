<?php
require_once __DIR__.'/monitoring_kbm_history.php';

/** Periode laporan memakai kalender WIB; minggu Senin–Minggu, sampai hari ini. */
function kbm_report_period(array $input,string $today): array {
    $mode=$input['periode_laporan']??'harian';$value=$input['tanggal']??$today;
    if(!is_string($mode)||!in_array($mode,['harian','mingguan','bulanan'],true)||!is_string($value))throw new InvalidArgumentException('Pilihan periode laporan tidak valid.');
    $date=kbm_date($value);kbm_date($today);
    if($value>$today)throw new InvalidArgumentException('Tanggal laporan tidak boleh melewati hari ini.');
    $start=$date;$end=$date;
    if($mode==='mingguan'){$start=$date->modify('-'.((int)$date->format('N')-1).' days');$end=$start->modify('+6 days');}
    if($mode==='bulanan'){$start=$date->modify('first day of this month');$end=$date->modify('last day of this month');}
    $days=[1=>'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
    return ['mode'=>$mode,'tanggal'=>$value,'hari'=>$days[(int)$date->format('N')],
        'tanggal_mulai'=>$start->format('Y-m-d'),'tanggal_selesai'=>min($today,$end->format('Y-m-d'))];
}
