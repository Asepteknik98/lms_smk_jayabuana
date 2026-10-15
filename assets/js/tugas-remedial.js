(() => {
    'use strict';
    const type = document.getElementById('prJenis');
    if (!type) return;
    const list = document.getElementById('prQuestionList');
    const editor = document.getElementById('prQuestions');
    const youtube = document.getElementById('prYoutube');
    const hints = {
        portofolio: 'Siswa mengunggah file PDF, DOCX, JPG, PNG, atau WEBP, maksimal 10 MB, beserta catatan opsional.',
        pilihan_ganda: 'Siswa menjawab soal pilihan ganda. Kunci jawaban hanya terlihat oleh guru untuk membantu penilaian.',
        esai: 'Siswa menuliskan jawaban untuk setiap soal essai yang Anda buat.',
        youtube: 'Siswa membuka video YouTube dari Anda, lalu menulis tanggapan sesuai instruksi.',
        merangkum: 'Siswa menuliskan hasil rangkuman sesuai bahan dan instruksi Anda.',
        video: 'Siswa membuat video, mengunggahnya ke YouTube atau penyimpanan lain, lalu mengirim tautan yang dapat diakses guru.'
    };
    const drafts = {esai: [], pilihan_ganda: []};
    let previous = '';
    function remember() {
        if (!(previous in drafts)) return;
        drafts[previous] = Array.from(list.children, card => ({
            text: card.querySelector('textarea').value,
            options: Array.from(card.querySelectorAll('input'), input => input.value),
            key: card.querySelector('select')?.value || '0'
        }));
    }
    function addQuestion(saved = {}) {
        if (list.children.length >= 100) return;
        const index = list.children.length;
        const card = document.createElement('fieldset');
        card.className = 'pr-question-card';
        card.innerHTML = `<legend class="float-none w-auto fs-6 fw-semibold">Soal ${index + 1}</legend>
            <label class="visually-hidden" for="prSoal${index}">Teks soal ${index + 1}</label>
            <textarea id="prSoal${index}" class="form-control mb-3" rows="3" name="soal[${index}][teks]" maxlength="5000" placeholder="Tuliskan pertanyaan..." required></textarea>`;
        card.querySelector('textarea').value = saved.text || '';
        if (type.value === 'pilihan_ganda') {
            for (let i = 0; i < 4; i++) {
                const label = document.createElement('label');
                label.className = 'pr-option';
                const letter = document.createElement('span');
                letter.textContent = String.fromCharCode(65 + i);
                const input = document.createElement('input');
                Object.assign(input, {className: 'form-control', name: `soal[${index}][opsi][${i}]`, required: true, maxLength: 2000, placeholder: `Pilihan ${letter.textContent}`, value: saved.options?.[i] || ''});
                label.append(letter, input);card.append(label);
            }
            const label = document.createElement('label');
            label.className = 'form-label mt-3';label.textContent = 'Kunci jawaban (hanya guru)';
            const select = document.createElement('select');
            select.className = 'form-select';select.name = `soal[${index}][kunci]`;select.required = true;
            for (let i = 0; i < 4; i++) {const option = document.createElement('option');option.value = String(i);option.textContent = String.fromCharCode(65 + i);select.append(option);}
            select.value = saved.key || '0';label.append(select);card.append(label);
        }
        const remove = document.createElement('button');
        remove.type = 'button';remove.className = 'btn btn-sm btn-outline-danger mt-2';remove.textContent = 'Hapus soal';
        remove.addEventListener('click', () => {
            if (list.children.length === 1) return;
            remember();drafts[previous].splice(index, 1);renderQuestions();
        });
        card.append(remove);list.append(card);
    }
    function renderQuestions() {
        list.replaceChildren();
        (drafts[type.value]?.length ? drafts[type.value] : [{}]).forEach(addQuestion);
    }
    function update() {
        remember();previous = type.value;
        const structured = ['pilihan_ganda', 'esai'].includes(type.value);
        editor.hidden = !structured;
        list.replaceChildren();
        if (structured) renderQuestions();
        const isYoutube = type.value === 'youtube';
        document.getElementById('prYoutubeField').hidden = !isYoutube;
        youtube.disabled = !isYoutube;youtube.required = isYoutube;
        document.getElementById('prAnswerText').textContent = hints[type.value] || 'Pilih jenis tugas untuk melihat cara siswa mengumpulkan jawaban.';
        document.getElementById('prTypeHint').textContent = structured ? 'Tambahkan soal pada bagian Daftar soal di bawah instruksi.' : 'Bentuk pengumpulan siswa mengikuti jenis tugas yang Anda pilih.';
    }
    type.addEventListener('change', update);
    document.getElementById('prAddQuestion').addEventListener('click', () => addQuestion());
    update();
})();
