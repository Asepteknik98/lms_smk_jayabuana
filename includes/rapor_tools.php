<?php
function rapor_filter_toolbar(string $id,string $placeholder,bool $incomplete=true): void { ?>
<div class="rapor-filter-toolbar" data-rapor-view-control>
<div class="rapor-search-field"><label class="form-label" for="<?= sanitize($id) ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?= sanitize($placeholder) ?></label><input type="search" id="<?= sanitize($id) ?>" class="form-control" data-filter-search placeholder="<?= sanitize($placeholder) ?>" autocomplete="off"></div>
<?php if($incomplete): ?><button type="button" class="btn btn-outline-secondary" data-filter-incomplete aria-pressed="false"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Lihat yang belum lengkap</span></button><?php endif ?>
<p class="small text-muted mb-0" data-filter-result role="status"></p>
</div>
<?php }
