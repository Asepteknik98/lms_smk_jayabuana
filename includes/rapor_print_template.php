<?php
// Print only the subjects selected and saved by the teacher, in saved order.
// Empty reports retain an unnamed grid to preserve the two-page design.
function rapor_print_template_rows(array $saved): array {
    if ($saved) return array_values($saved);
    $rows=[];
    for ($i=0;$i<9;$i++) {
        $rows[]=['nama_mapel'=>'','kelompok'=>$i<4?'Umum':'Kejuruan',
            'nama_guru'=>'','nilai_akhir'=>null,'deskripsi'=>'','saran'=>''];
    }
    return $rows;
}
