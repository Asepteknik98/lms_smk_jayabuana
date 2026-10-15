<?php
function pr_render_type_info(array $task,bool $teacher=false): void {
    $type=$task['jenis']??'teks';$config=json_decode($task['konfigurasi']??'[]',true)?:[];
    if(isset(pr_types()[$type]))echo '<span class="badge bg-primary mb-2">'.sanitize(pr_types()[$type]).'</span>';
    $url=$config['youtube_url']??'';
    if($type==='youtube'&&is_string($url)&&pr_http_url($url))echo '<p><a class="btn btn-outline-primary btn-sm" href="'.sanitize($url).'" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-youtube me-1"></i>Tonton Video YouTube</a></p>';
    if($teacher&&!empty($config['soal'])){
        echo '<details class="mb-3"><summary>Lihat soal'.($type==='pilihan_ganda'?' dan kunci jawaban':'').'</summary>';
        foreach($config['soal'] as $i=>$q){
            echo '<div class="border rounded p-3 mt-2"><p style="white-space:pre-wrap">'.($i+1).'. '.sanitize($q['teks']).'</p>';
            foreach($q['opsi']??[] as $j=>$option)echo '<div>'.chr(65+$j).'. '.sanitize($option).($j===$q['kunci']?' <strong class="text-success">(Kunci)</strong>':'').'</div>';
            echo '</div>';
        }
        echo '</details>';
    }
}

function pr_render_answer_fields(array $task): void {
    $type=$task['jenis']??'teks';$config=json_decode($task['konfigurasi']??'[]',true)?:[];$id=(int)$task['id'];
    if(in_array($type,['pilihan_ganda','esai'],true)){
        foreach($config['soal']??[] as $i=>$q){
            echo '<fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto fs-6">Soal '.($i+1).'</legend><p style="white-space:pre-wrap">'.sanitize($q['teks']).'</p>';
            if($type==='pilihan_ganda'){
                foreach($q['opsi'] as $j=>$option)echo '<label class="d-flex gap-2 border rounded p-2 mb-2"><input class="form-check-input flex-shrink-0" type="radio" name="jawaban_soal['.$i.']" value="'.$j.'" required><span>'.chr(65+$j).'. '.sanitize($option).'</span></label>';
            }else echo '<label class="visually-hidden" for="prJawaban'.$id.'_'.$i.'">Jawaban soal '.($i+1).'</label><textarea id="prJawaban'.$id.'_'.$i.'" class="form-control" name="jawaban_soal['.$i.']" rows="4" maxlength="5000" required></textarea>';
            echo '</fieldset>';
        }
        return;
    }
    if($type==='portofolio')echo '<label class="form-label" for="prFile'.$id.'">File portofolio</label><input id="prFile'.$id.'" type="file" name="file_portofolio" class="form-control mb-2" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" required><p class="small text-muted">PDF, DOCX, JPG, PNG, atau WEBP. Maksimal 10 MB.</p>';
    $labels=['video'=>'Tautan video hasil karya','merangkum'=>'Hasil rangkuman','youtube'=>'Tanggapan / hasil pengamatan video','portofolio'=>'Catatan portofolio (opsional)'];
    echo '<label for="prJawaban'.$id.'" class="form-label">'.($labels[$type]??'Jawaban remedial').'</label>';
    if($type==='video')echo '<input id="prJawaban'.$id.'" type="url" name="jawaban" class="form-control mb-3" maxlength="2000" required placeholder="https://..."><p class="small text-muted">Pastikan tautan video dapat diakses oleh guru.</p>';
    else echo '<textarea id="prJawaban'.$id.'" name="jawaban" class="form-control mb-3" rows="5" maxlength="30000" '.($type==='portofolio'?'':'required').' placeholder="Tulis jawaban Anda..."></textarea>';
}

function pr_render_file_link(array $row,int $assignment,int $sid): void {
    if(is_string($row['jawaban']??null)&&pr_http_url(trim($row['jawaban'])))echo '<a class="btn btn-sm btn-outline-primary my-2 me-2" href="'.sanitize(trim($row['jawaban'])).'" target="_blank" rel="noopener noreferrer">Buka tautan hasil pekerjaan</a>';
    if(!empty($row['file_jawaban']))echo '<a class="btn btn-sm btn-outline-primary my-2" href="../remedial_file.php?id='.$assignment.'&amp;siswa='.$sid.'"><i class="fa-solid fa-paperclip me-1"></i>'.sanitize($row['nama_file']).'</a>';
}
