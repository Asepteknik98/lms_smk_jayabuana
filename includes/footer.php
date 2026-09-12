<style>
    .app-global-footer {
        margin-top: auto;
        padding: 18px 20px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
        color: #64748b;
        font-size: .82rem;
        text-align: center;
        letter-spacing: .01em;
    }
    #page-content-wrapper {
        display: flex;
        flex-direction: column;
    }
    #page-content-wrapper > .app-global-footer {
        width: 100%;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('page-content-wrapper');
    if (page && !page.querySelector('.app-global-footer')) {
        const footer = document.createElement('footer');
        footer.className = 'app-global-footer';
        footer.innerHTML = '&copy; Copyright SMKS JAYA BUANA 2026 <strong>V.1.0.0</strong>';
        page.appendChild(footer);
    }
});
</script>
</div> <!-- End #wrapper -->

<?php
$role_id_pengumuman = (int)($_SESSION['role_id'] ?? 0);
$tampilkan_pengumuman_abu = in_array($role_id_pengumuman, [2, 3], true);
$halaman_dashboard_pengumuman = preg_match('~/(guru|siswa)/index\.php$~', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '')) === 1;
?>
<?php if ($tampilkan_pengumuman_abu): ?>
<style>
    .volcanic-alert-banner {
        display:flex; align-items:center; gap:12px; padding:13px 15px; margin-bottom:16px;
        color:#172554; background:linear-gradient(135deg,#fff7cc,#ffed8a); border:1px solid #f5cf43;
        border-radius:14px; box-shadow:0 5px 16px rgba(113,78,0,.08);
    }
    .volcanic-alert-banner .alert-icon {
        width:42px; height:42px; display:grid; place-items:center; flex:0 0 42px;
        color:#172554; background:#ffd633; border-radius:12px; font-size:1.2rem;
    }
    .volcanic-alert-banner .alert-copy { min-width:0; flex:1; }
    .volcanic-alert-banner .alert-copy small { display:block; color:#725b13; line-height:1.35; }
    .volcanic-notice-modal .modal-content { border:0; border-radius:18px; overflow:hidden; }
    .volcanic-notice-modal .modal-header { background:#fff7cc; border-bottom-color:#f5df87; }
    .volcanic-notice-image { display:block; width:100%; max-height:68vh; object-fit:contain; background:#eef2f7; }
    @media(max-width:575.98px) {
        .volcanic-alert-banner { align-items:flex-start; flex-wrap:wrap; }
        .volcanic-alert-banner .btn { width:100%; }
        .volcanic-notice-modal .modal-dialog { margin:.6rem; }
        .volcanic-notice-image { max-height:67vh; }
    }
</style>

<div class="modal fade volcanic-notice-modal" id="volcanicAshNotice" tabindex="-1" aria-labelledby="volcanicAshNoticeTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="volcanicAshNoticeTitle" aria-live="polite"><i class="fa-solid fa-circle-info text-primary me-2"></i><span id="noticeTitleText">Panduan Penggunaan LMS</span></h2>
            </div>
            <div class="modal-body p-0">
                <img src="../assets/pengumuman/panduan.jpg" class="volcanic-notice-image" alt="Panduan penggunaan LMS SMK Jaya Buana untuk siswa: absen online, membuka materi, mengirim tugas, melihat riwayat absensi, semua materi, dan nilai.">
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted" id="noticeCaption" aria-live="polite">1 / 2 — Pelajari panduan penggunaan LMS SMK Jaya Buana</small>
                <div class="d-flex flex-wrap gap-2">
                    <a href="https://whatsapp.com/channel/0029VaavIAl7Noa3wxlyXP24" class="btn btn-success d-none" id="noticeChannel" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp me-1"></i>Cek Saluran</a>
                    <button type="button" class="btn btn-primary px-4" id="noticeUnderstand"><i class="fa-solid fa-check me-1"></i>Saya Mengerti</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- jQuery, Bootstrap, DataTables, Chart.js & SweetAlert2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if(!empty($needs_datatables)): ?><script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($tampilkan_pengumuman_abu): ?><script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('volcanicAshNotice');
    if (!modalElement || typeof bootstrap === 'undefined') return;

    const noticeModal = bootstrap.Modal.getOrCreateInstance(modalElement, { backdrop: 'static', keyboard: false });
    const noticeImage = modalElement.querySelector('.volcanic-notice-image');
    const noticeTitle = document.getElementById('noticeTitleText');
    const noticeCaption = document.getElementById('noticeCaption');
    const noticeChannel = document.getElementById('noticeChannel');
    const notices = [
        { src: '../assets/pengumuman/panduan.jpg', alt: 'Panduan penggunaan LMS SMK Jaya Buana untuk siswa: absen online, membuka materi, mengirim tugas, melihat riwayat absensi, semua materi, dan nilai.', title: 'Panduan Penggunaan LMS', caption: 'Pelajari panduan penggunaan LMS SMK Jaya Buana' },
        { src: '../assets/pengumuman/saluran.jpg', alt: 'Ajakan mengikuti Saluran WhatsApp SMK Jaya Buana untuk informasi kegiatan sekolah, jadwal dan agenda, pengumuman penting, serta berita terbaru.', title: 'Saluran WhatsApp SMK Jaya Buana', caption: 'Ikuti saluran WhatsApp sekolah untuk mendapatkan informasi terbaru' }
    ];
    let noticeIndex = 0;
    let noticesCompleted = false;
    function renderNotice() {
        const notice = notices[noticeIndex];
        noticeImage.src = notice.src;
        noticeImage.alt = notice.alt;
        noticeTitle.textContent = notice.title;
        noticeChannel.classList.toggle('d-none', notice.src !== '../assets/pengumuman/saluran.jpg');
        noticeCaption.textContent = (noticeIndex + 1) + ' / ' + notices.length + ' — ' + notice.caption;
        modalElement.querySelector('.modal-body').scrollTop = 0;
    }
    modalElement.addEventListener('show.bs.modal', function () {
        noticeIndex = 0;
        noticesCompleted = false;
        renderNotice();
    });
    document.getElementById('noticeUnderstand').addEventListener('click', function () {
        if (noticeIndex < notices.length - 1) {
            noticeIndex++;
            renderNotice();
            return;
        }
        noticesCompleted = true;
        noticeModal.hide();
    });
    modalElement.addEventListener('hide.bs.modal', function (event) {
        if (!noticesCompleted) event.preventDefault();
    });
    const now = new Date();
    const dateKey = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
    const storageKey = 'lms_volcanic_ash_notice_<?= (int)($_SESSION['user_id'] ?? 0) ?>';

    <?php if ($halaman_dashboard_pengumuman): ?>
    const dashboardMain = document.querySelector('#page-content-wrapper > main');
    if (dashboardMain && !dashboardMain.querySelector('[data-volcanic-notice-open]')) {
        const banner = document.createElement('aside');
        banner.className = 'volcanic-alert-banner';
        banner.setAttribute('aria-label', 'Panduan LMS dan saluran WhatsApp sekolah');
        banner.innerHTML = '<span class="alert-icon"><i class="fa-solid fa-bullhorn"></i></span><span class="alert-copy"><strong>Pengumuman Sekolah</strong><small>Lihat panduan LMS dan saluran WhatsApp sekolah.</small></span><button type="button" class="btn btn-sm btn-warning fw-semibold" data-volcanic-notice-open><i class="fa-solid fa-image me-1"></i>Lihat Pengumuman</button>';
        dashboardMain.prepend(banner);
        banner.querySelector('[data-volcanic-notice-open]').addEventListener('click', function () { noticeModal.show(); });
    }
    <?php endif; ?>

    let lastSeen = '';
    try { lastSeen = localStorage.getItem(storageKey) || ''; } catch (error) {}
    if (lastSeen !== dateKey) noticeModal.show();

    modalElement.addEventListener('hidden.bs.modal', function () {
        if (noticesCompleted) {
            try { localStorage.setItem(storageKey, dateKey); } catch (error) {}
        }
    });
});
</script><?php endif; ?>
<?php if(in_array((int)($_SESSION['role_id']??0),[2,3],true)): ?><script>
if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js',{scope:'/'}).catch(()=>{}))}
let deferredPwaPrompt=null;
<?php if((int)($_SESSION['role_id']??0)===2): ?>document.addEventListener('DOMContentLoaded',()=>{const header=document.querySelector('#page-content-wrapper > nav:first-child');if(!header||header.querySelector('[data-install-pwa]'))return;const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-primary d-none ms-auto flex-shrink-0';button.setAttribute('data-install-pwa','');button.innerHTML='<i class="fa-solid fa-mobile-screen-button me-1"></i>Pasang Aplikasi';const target=header.querySelector(':scope > .d-flex')||header;target.appendChild(button)});
<?php endif; ?>
const pwaButtons=()=>document.querySelectorAll('[data-install-pwa]');
const isStandalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);
function showPwaButtons(){if(!isStandalone)pwaButtons().forEach(button=>button.classList.remove('d-none'))}
window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();deferredPwaPrompt=event;showPwaButtons()});
document.addEventListener('DOMContentLoaded',()=>{if(deferredPwaPrompt||isIos)showPwaButtons();pwaButtons().forEach(button=>button.addEventListener('click',async()=>{if(deferredPwaPrompt){deferredPwaPrompt.prompt();await deferredPwaPrompt.userChoice;deferredPwaPrompt=null;button.classList.add('d-none')}else if(isIos){Swal.fire({icon:'info',title:'Pasang di iPhone',html:'Ketuk tombol <strong>Bagikan</strong> di Safari, lalu pilih <strong>Tambahkan ke Layar Utama</strong>.',confirmButtonColor:'#1769e0'})}else{Swal.fire({icon:'info',title:'Pasang LMS',text:'Buka menu browser lalu pilih “Instal aplikasi” atau “Tambahkan ke layar utama”.',confirmButtonColor:'#1769e0'})}}))});
</script><?php endif ?>
</body>
</html>
