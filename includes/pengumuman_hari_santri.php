<?php
// Sama seperti pengumuman awal: sekali per sesi login untuk guru/siswa.
if(empty($_SESSION['user_id'])||!in_array((int)($_SESSION['role_id']??0),[2,3],true))return;
$schoolNoticeFile=__DIR__.'/../assets/pengumuman/TKA.jpeg';
if(!is_file($schoolNoticeFile))return;
$schoolNoticeVersion=(int)filemtime($schoolNoticeFile);
if(($_SESSION['hari-santri_announcement_version']??null)===$schoolNoticeVersion)return;
$_SESSION['hari-santri_announcement_version']=$schoolNoticeVersion;
$schoolNoticeImage='../assets/pengumuman/TKA.jpeg?v='.$schoolNoticeVersion;
?>
<!-- CSS popup disertakan langsung agar ukuran tidak bergantung pada cache/unggahan CSS terpisah. -->
<style>
#hari-santri-announcement{box-sizing:border-box;width:min(640px,calc(100vw - 32px));max-width:640px;max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);font-family:inherit;padding:0;border:1px solid #e1e8f1;border-radius:22px;background:#fff;box-shadow:0 25px 90px #10274145;color:#243f5d;overflow:auto}
#hari-santri-announcement::backdrop{background:#152a4780;backdrop-filter:blur(4px)}
#hari-santri-announcement .hari-santri-layout{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr)}
#hari-santri-announcement .hari-santri-poster{display:flex;align-items:center;justify-content:center;height:min(440px,calc(100dvh - 64px));box-sizing:border-box;min-width:0;padding:14px;background:#f5f8fc}
#hari-santri-announcement .hari-santri-poster>a{display:flex;align-items:center;justify-content:center;width:100%;height:100%;min-width:0}
#hari-santri-announcement img{display:block;width:auto;max-width:100%;height:auto;max-height:100%;object-fit:contain;border-radius:11px}
#hari-santri-announcement .hari-santri-content{display:flex;flex-direction:column;justify-content:center;padding:24px;min-width:0}
#hari-santri-announcement .hari-santri-eyebrow{font-size:.64rem;letter-spacing:.1em;color:#a18344;font-weight:700;margin-bottom:16px}
#hari-santri-announcement h2{font-size:1.35rem;font-weight:750;line-height:1.35;margin:0 0 16px;color:#244567}
#hari-santri-announcement p{font-size:.8rem;color:#7b8ba0;line-height:1.8;margin:0 0 14px}
#hari-santri-announcement .hari-santri-full{font-size:.75rem;color:#466f9f;text-underline-offset:3px;display:inline-block;padding:8px 0;min-height:36px}
#hari-santri-announcement form{margin-top:24px}
#hari-santri-announcement button{width:100%;min-height:46px;background:#234b74;color:#fff;border:0;border-radius:9px;font-size:.85rem;font-weight:600;padding:12px 18px;cursor:pointer}
#hari-santri-announcement button:hover{background:#30618e}
#hari-santri-announcement button:focus-visible,#hari-santri-announcement a:focus-visible{outline:3px solid #b69855;outline-offset:3px}
@media(max-width:640px){#hari-santri-announcement{width:min(360px,calc(100vw - 24px));max-height:calc(100dvh - 24px);border-radius:17px}#hari-santri-announcement .hari-santri-layout{grid-template-columns:1fr}#hari-santri-announcement .hari-santri-poster{height:clamp(140px,calc(100dvh - 290px),380px);padding:12px 12px 0;background:#fff}#hari-santri-announcement img{max-width:100%;max-height:100%}#hari-santri-announcement .hari-santri-content{padding:14px 18px}#hari-santri-announcement .hari-santri-eyebrow{font-size:.57rem;margin-bottom:8px}#hari-santri-announcement h2{font-size:1.2rem;margin-bottom:8px}#hari-santri-announcement h2 br{display:none}#hari-santri-announcement p{font-size:.76rem;margin-bottom:5px}#hari-santri-announcement form{margin-top:12px}}
</style>
<dialog id="hari-santri-announcement" aria-labelledby="hari-santri-title" aria-describedby="hari-santri-description">
<div class="hari-santri-layout">
<div class="hari-santri-poster"><a href="<?= $schoolNoticeImage ?>" target="_blank" rel="noopener" aria-label="Buka poster Tes Kemampuan Akademik ukuran penuh pada tab baru"><img src="<?= $schoolNoticeImage ?>" alt="Poster Tes Kemampuan Akademik SMK JAYA BUANA Tahun 2026." width="1280" height="1600" decoding="async"></a></div>
<div class="hari-santri-content"><span class="hari-santri-eyebrow">PENGUMUMAN SEKOLAH</span><h2 id="hari-santri-title">Tes Kemampuan Akademik</h2><p id="hari-santri-description">SMK JAYA BUANA Tahun 2026.</p><a class="hari-santri-full" href="<?= $schoolNoticeImage ?>" target="_blank" rel="noopener">Buka poster ukuran penuh <span aria-hidden="true">&nearr;</span></a><form method="dialog"><button type="submit" autofocus>Saya Mengerti</button></form></div>
</div></dialog>
<script>
(()=>{
    const dialog=document.getElementById('hari-santri-announcement');
    let previousOverflow='';
    const show=()=>{if(dialog.open)return;previousOverflow=document.documentElement.style.overflow;dialog.showModal();document.documentElement.style.overflow='hidden';};
    dialog.addEventListener('cancel',event=>event.preventDefault());
    dialog.addEventListener('close',()=>{document.documentElement.style.overflow=previousOverflow;});
    const start=show;
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
</script>
