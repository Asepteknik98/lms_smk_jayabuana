<?php
// Session dihapus saat logout, sehingga pengumuman muncul lagi setelah login berikutnya.
if (empty($_SESSION['user_id']) || !in_array((int)($_SESSION['role_id'] ?? 0), [2, 3], true)
    || !empty($_SESSION['milad_announcement_shown'])) {
    return;
}
$_SESSION['milad_announcement_shown'] = true;
?>
<style>
    #milad-announcement {
        width: min(820px, calc(100% - 32px));
        max-width: none;
        max-height: calc(100vh - 32px);
        max-height: calc(100dvh - 32px);
        margin: auto;
        padding: 0;
        border: 1px solid rgba(255, 255, 255, .7);
        border-radius: 24px;
        background: #fffdf9;
        color: #142d49;
        box-shadow: 0 32px 100px rgba(4, 18, 36, .35);
        overflow: auto;
        overscroll-behavior: contain;
    }
    #milad-announcement::backdrop {
        background: rgba(9, 24, 43, .68);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }
    #milad-announcement .milad-layout { display: grid; grid-template-columns: 1.05fr 1fr; }
    #milad-announcement .milad-poster {
        display: flex;
        align-items: center;
        padding: 18px;
        background: linear-gradient(145deg, #edf4f7, #e1ebef);
    }
    #milad-announcement img {
        display: block;
        width: 100%;
        max-height: calc(100vh - 72px);
        max-height: calc(100dvh - 72px);
        object-fit: contain;
        border-radius: 12px;
    }
    #milad-announcement .milad-content {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 36px 30px;
        text-align: center;
        background: radial-gradient(ellipse at top right, rgba(224, 181, 91, .13), transparent 65%);
    }
    #milad-announcement .milad-eyebrow {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 20px;
        color: #876727;
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    #milad-announcement .milad-eyebrow::before,
    #milad-announcement .milad-eyebrow::after { content: ''; width: 22px; height: 1px; background: #ccb175; }
    #milad-announcement h2 { margin: 0 0 18px; font-size: clamp(1.45rem, 3vw, 1.85rem); font-weight: 750; line-height: 1.3; letter-spacing: -.035em; }
    #milad-announcement h2 span { display: block; }
    #milad-announcement h2 .milad-greeting { margin-bottom: 5px; font-size: .7em; font-weight: 500; letter-spacing: 0; color: #5f7081; }
    #milad-announcement #milad-description { margin: 0; color: #627080; font-size: .9rem; line-height: 1.8; }
    #milad-announcement .milad-motto { margin: 22px 0 0; color: #876727; font-size: .76rem; font-weight: 650; letter-spacing: .07em; }
    #milad-announcement form { margin: 28px 0 0; }
    #milad-announcement button {
        width: 100%;
        min-height: 48px;
        padding: 12px 24px;
        border: 1px solid #1c3d60;
        border-radius: 12px;
        background: linear-gradient(135deg, #23476d, #112c49);
        box-shadow: 0 6px 16px rgba(17, 44, 73, .16);
        color: #fff;
        font: inherit;
        font-size: .88rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s, box-shadow .2s;
    }
    #milad-announcement button:hover { background: #2a5077; box-shadow: 0 8px 20px rgba(17, 44, 73, .22); }
    #milad-announcement button:focus-visible { outline: 3px solid #b99043; outline-offset: 4px; }
    @media (max-width: 639px) {
        #milad-announcement { width: min(400px, calc(100% - 28px)); border-radius: 22px; }
        #milad-announcement .milad-layout { grid-template-columns: 1fr; }
        #milad-announcement .milad-poster { padding: 12px 12px 0; background: #fffdf9; }
        #milad-announcement img { max-height: max(180px, calc(100vh - 320px)); max-height: max(180px, calc(100dvh - 320px)); border-radius: 13px; }
        #milad-announcement .milad-content { padding: 20px 22px; }
        #milad-announcement .milad-eyebrow { margin-bottom: 12px; font-size: .58rem; }
        #milad-announcement h2 { margin-bottom: 10px; font-size: 1.4rem; }
        #milad-announcement #milad-description { font-size: .8rem; line-height: 1.7; }
        #milad-announcement .milad-motto { margin-top: 14px; font-size: .7rem; }
        #milad-announcement form { margin-top: 18px; }
    }
    @media (prefers-reduced-motion: reduce) {
        #milad-announcement button { transition: none; }
    }
</style>
<dialog id="milad-announcement" aria-labelledby="milad-title" aria-describedby="milad-description milad-motto">
    <div class="milad-layout">
        <div class="milad-poster">
            <img src="../assets/pengumuman/milad.jpg" alt="Pengumuman Milad sekolah">
        </div>
        <div class="milad-content">
            <div class="milad-eyebrow" aria-hidden="true">14 Tahun Bersama</div>
            <h2 id="milad-title"><span class="milad-greeting">Selamat Milad ke-14</span><span>SMK Jaya Buana!</span></h2>
            <p id="milad-description">14 Tahun Bersama, terus tumbuh, berkarya, dan melangkah maju menuju masa depan.</p>
            <p id="milad-motto" class="milad-motto">Muda &bull; Mandiri &bull; Maju</p>
            <form method="dialog">
                <button type="submit" autofocus>Saya Mengerti</button>
            </form>
        </div>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('milad-announcement');
    const previousOverflow = document.documentElement.style.overflow;
    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.addEventListener('close', () => {
        document.documentElement.style.overflow = previousOverflow;
    });
    dialog.showModal();
    document.documentElement.style.overflow = 'hidden';
})();
</script>
