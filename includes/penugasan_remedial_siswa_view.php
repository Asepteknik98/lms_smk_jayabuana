<?php
function pr_render_student(PDO $db,int $sid): void {
    $success=$_SESSION['pr_student_success']??'';$error=$_SESSION['pr_student_error']??'';
    unset($_SESSION['pr_student_success'],$_SESSION['pr_student_error']);
    if($success)echo '<div class="alert alert-success">'.sanitize($success).'</div>';
    if($error)echo '<div class="alert alert-danger">'.sanitize($error).'</div>';
    if(!pr_ready($db)||!rt_cancel_ready($db))return;
    $tasks=pr_student_tasks($db,$sid);if(!$tasks)return;
?>
<section id="tugas-remedial" class="mb-4"><h4 class="fw-bold">Tugas Remedial</h4><p class="text-muted">Tugas khusus remedial dari guru mata pelajaran Anda.</p>
<?php foreach($tasks as $task): ?><div class="card card-body mb-3 border-warning"><div><span class="badge bg-warning text-dark mb-2">Remedial</span></div><h5><?= sanitize($task['judul']) ?></h5><p class="text-muted mb-1"><?= sanitize($task['nama_mapel'].' · '.$task['nama_guru']) ?></p><small>Tugas asal: <?= sanitize($task['tugas_asal']) ?> · Tenggat: <?= sanitize($task['deadline']) ?></small><p class="mt-3" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= sanitize($task['instruksi']) ?></p>
<?php if($task['dikumpulkan_pada']): ?><div class="alert alert-info mb-0"><?php if($task['nilai_remedial']!==null): ?>Nilai remedial: <strong><?= sanitize((string)$task['nilai_remedial']) ?></strong><?= $task['dibatalkan_pada']?' — dibatalkan oleh guru; nilai akhir mengikuti rekap terbaru.':' — nilai akhir mengikuti nilai tertinggi yang masih berlaku.' ?><?php else: ?>Sudah dikumpulkan. Menunggu penilaian guru.<?php endif ?><details class="mt-2"><summary>Jawaban saya</summary><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= sanitize($task['jawaban']) ?></div></details></div>
<?php elseif(strtotime($task['deadline'])<time()): ?><p class="text-danger mb-0">Tenggat berakhir. Belum mengumpulkan.</p>
<?php else: ?><form method="post" action="tugas_remedial_action.php"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="penugasan_id" value="<?= (int)$task['id'] ?>"><label for="prJawaban<?= (int)$task['id'] ?>" class="form-label">Jawaban remedial</label><textarea id="prJawaban<?= (int)$task['id'] ?>" name="jawaban" class="form-control mb-3" rows="5" maxlength="30000" required placeholder="Tulis jawaban atau tautan hasil pekerjaan Anda..."></textarea><button class="btn btn-primary">Kirim Jawaban Remedial</button><small class="d-block text-muted mt-2">Periksa jawaban sebelum dikirim. Pengumpulan hanya satu kali.</small></form><?php endif ?></div><?php endforeach ?></section>
<?php }
