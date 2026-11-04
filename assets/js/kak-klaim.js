const claimDialog = document.querySelector('#kakClaimDialog');
const claimTrigger = document.querySelector('[data-open-claims]');
if (claimDialog && claimTrigger) {
    // Dialog ditempatkan di body agar tidak dibatasi area gulir dashboard.
    document.body.appendChild(claimDialog);
    const openClaims = () => {
        if (!claimDialog.open) claimDialog.showModal();
        document.documentElement.classList.add('claim-dialog-open');
    };
    claimTrigger.addEventListener('click', openClaims);
    claimDialog.querySelector('[data-close-claims]').addEventListener('click', () => claimDialog.close());
    claimDialog.addEventListener('click', event => {
        if (event.target !== claimDialog) return;
        const bounds = claimDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) claimDialog.close();
    });
    claimDialog.addEventListener('close', () => {
        if (claimDialog.open) return;
        document.documentElement.classList.remove('claim-dialog-open');
        if (location.hash === '#klaimAktivitas') history.replaceState(null, '', location.pathname + location.search);
        claimTrigger.focus({ preventScroll: true });
    });
    const openRequested = () => {
        if (location.hash === '#klaimAktivitas') openClaims();
    };
    window.addEventListener('hashchange', openRequested);
    if (claimDialog.dataset.autoOpen === 'true' || location.hash === '#klaimAktivitas') openClaims();
}

document.querySelectorAll('.claim-form').forEach(form => {
    const gallery = form.querySelector('[data-claim-file]');
    const camera = form.querySelector('[data-claim-camera]');
    const inputs = [gallery, camera];
    const preview = form.querySelector('[data-claim-preview]');
    const result = form.querySelector('[data-claim-result]');
    const filename = form.querySelector('[data-claim-filename]');
    const error = form.querySelector('[data-claim-error]');
    let objectUrl;
    function clearPreview() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        preview.hidden = true;
        result.hidden = true;
        preview.removeAttribute('src');
        filename.textContent = '';
        error.hidden = true;
        inputs.forEach(input => input.setCustomValidity(''));
    }
    inputs.forEach(input => input.addEventListener('change', () => {
        const file = input.files[0];
        // Membatalkan pemilih foto mempertahankan bukti dari sumber lainnya.
        if (!file) {
            gallery.required = !inputs.some(item => item.files.length);
            if (!inputs.some(item => item.files.length)) clearPreview();
            return;
        }
        inputs.filter(item => item !== input).forEach(item => { item.value = ''; });
        clearPreview();
        gallery.required = input === gallery;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            const message = 'Pilih foto JPG, PNG, atau WebP maksimal 5 MB.';
            input.setCustomValidity(message);
            error.textContent = message;
            error.hidden = false;
            return;
        }
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.hidden = false;
        result.hidden = false;
        filename.textContent = file.name;
    }));
    form.querySelector('[data-claim-remove]').addEventListener('click', () => {
        inputs.forEach(input => { input.value = ''; });
        clearPreview();
        gallery.required = true;
        gallery.focus();
    });
    preview.addEventListener('error', () => {
        const input = inputs.find(item => item.files.length);
        if (!input) return;
        const message = 'Foto tidak dapat dibuka. Ambil ulang atau pilih foto lain.';
        clearPreview();
        input.setCustomValidity(message);
        error.textContent = message;
        error.hidden = false;
    });
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Mengirim bukti…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = false;
        button.textContent = 'Kirim Klaim';
        form.removeAttribute('aria-busy');
    });
});

const claimSlider = document.querySelector('.claim-event-list');
const claimSliderControls = document.querySelector('[data-claim-slider-controls]');
if (claimSlider && claimSliderControls) {
    const previous = claimSliderControls.querySelector('[data-claim-prev]');
    const next = claimSliderControls.querySelector('[data-claim-next]');
    const mobile = window.matchMedia('(max-width:575.98px)');
    const update = () => {
        if (mobile.matches) {
            const viewport = claimSlider.getBoundingClientRect();
            const visibleCards = [...claimSlider.children].filter(card => {
                const bounds = card.getBoundingClientRect();
                return bounds.right > viewport.left + 8 && bounds.left < viewport.right - 8;
            });
            const height = Math.ceil(Math.max(0, ...visibleCards.map(card => card.getBoundingClientRect().height)) + 18);
            if (height > 18 && claimSlider.style.height !== height + 'px') claimSlider.style.height = height + 'px';
        } else claimSlider.style.removeProperty('height');
        const overflowing = claimSlider.scrollWidth > claimSlider.clientWidth + 2;
        claimSliderControls.hidden = !mobile.matches || !overflowing;
        previous.disabled = claimSlider.scrollLeft <= 2;
        next.disabled = claimSlider.scrollLeft + claimSlider.clientWidth >= claimSlider.scrollWidth - 2;
    };
    const move = direction => claimSlider.scrollBy({left:direction * (claimSlider.clientWidth + 8),behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    claimSlider.addEventListener('scroll', update, {passive:true});
    window.addEventListener('resize', update);
    claimSlider.querySelectorAll('.claim-upload').forEach(details => details.addEventListener('toggle', () => {
        if (details.open && mobile.matches) {
            const card = details.closest('.claim-event');
            claimSlider.scrollTo({left:card.offsetLeft - claimSlider.firstElementChild.offsetLeft,behavior:'instant'});
        }
        update();
    }));
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(update);
        observer.observe(claimSlider);
        [...claimSlider.children].forEach(card => observer.observe(card));
    }
    update();
}
