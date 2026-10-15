<?php require_once __DIR__.'/../includes/rekap_nilai_guru_page.php'; ?>
<script>
document.querySelectorAll('.score-toggle').forEach(button => {
 button.addEventListener('click', () => {
  const cell=button.closest('td'),input=cell.querySelector('.score-input'),mode=cell.querySelector('.score-source'),label=cell.querySelector('.score-label');
  const manual=mode.value!=='manual';mode.value=manual?'manual':'auto';input.disabled=!manual;input.required=manual;
  if(!manual)input.value=cell.dataset.auto;
  label.textContent=manual?'Manual':'Otomatis';button.textContent=manual?'Kembali ke Otomatis':'Isi Manual';button.setAttribute('aria-pressed',String(manual));input.classList.toggle('border-warning',manual);if(manual)input.focus();
 });
});
</script>
