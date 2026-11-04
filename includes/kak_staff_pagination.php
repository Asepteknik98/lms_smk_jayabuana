<?php if($panel_records['total']>0): ?>
<nav class="kak-panel-pagination" aria-label="<?=$panel_page_key==='claims_page'?'Halaman klaim':'Halaman acara'?>">
<small><?=($panel_records['page']-1)*10+1?>–<?=min($panel_records['page']*10,$panel_records['total'])?> dari <?=$panel_records['total']?></small>
<div>
<?php if($panel_records['page']>1):?><a class="btn btn-outline-primary" href="<?=sanitize($panel_url([$panel_page_key=>$panel_records['page']-1],$panel_anchor))?>" aria-label="Halaman sebelumnya"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a><?php endif ?>
<span><?=$panel_records['page']?> / <?=$panel_records['pages']?></span>
<?php if($panel_records['page']<$panel_records['pages']):?><a class="btn btn-outline-primary" href="<?=sanitize($panel_url([$panel_page_key=>$panel_records['page']+1],$panel_anchor))?>" aria-label="Halaman berikutnya"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif ?>
</div></nav>
<?php endif ?>
