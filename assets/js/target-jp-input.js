(function(root,factory){
    const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.mount(root.document);
})(typeof window==='undefined'?globalThis:window,function(){
    'use strict';
    function suggestedQuantity(data){
        if(data?.working_day===false||!data?.ready||!data.configured||!(data.harian?.target>0))return null;
        const n=data.harian.kurang;return Number.isInteger(n)&&n>=1&&n<=6?n:null;
    }
    function mount(doc){
        const panel=doc.getElementById('rmInputTarget');if(!panel)return;
        const teacher=doc.getElementById('rmTeacher'),date=doc.getElementById('rmEntryDate'),start=doc.getElementById('rmStart'),end=doc.getElementById('rmEnd');
        const status=doc.getElementById('rmInputTargetMessage'),refresh=doc.getElementById('rmInputTargetRefresh');
        const editing=panel.dataset.editing==='1',format=new Intl.NumberFormat('id-ID',{maximumFractionDigits:2});
        let generation=0,request=null,dirty=false,autoChanging=false,autofilled=false;
        const field=(key,value)=>{doc.getElementById('rmInputTarget_'+key).textContent=value;};
        function clear(){for(const key of ['day','week','actual','remaining'])field(key,'—');}
        function setRange(quantity){autoChanging=true;start.value='1';end.value=String(quantity);end.dispatchEvent(new Event('change',{bubbles:true}));autoChanging=false;}
        for(const input of [start,end])input.addEventListener('change',()=>{if(!autoChanging){dirty=true;autofilled=false;}});
        async function load(changed=false){
            const version=++generation;request?.abort();request=null;
            if(changed){if(autofilled&&!editing)setRange(1);dirty=false;autofilled=false;}
            clear();
            if(!teacher.value||!date.value){panel.setAttribute('aria-busy','false');status.textContent='Pilih guru dan tanggal untuk melihat target JP.';return;}
            const guru=teacher.value,tanggal=date.value;const controller=new AbortController();request=controller;
            const timeout=setTimeout(()=>controller.abort(),10000);panel.setAttribute('aria-busy','true');status.textContent='Memuat acuan target JP…';
            try{
                const params=new URLSearchParams({guru_id:guru,tanggal});
                const response=await fetch(panel.dataset.endpoint+'?'+params,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
                if(response.redirected||!response.headers.get('content-type')?.includes('application/json'))throw Error('Sesi mungkin berakhir. Muat ulang halaman untuk memeriksa akses.');
                const payload=await response.json();if(!response.ok||!payload.ok)throw Error(payload.message||'Target belum dapat dimuat.');
                if(version!==generation)return;const data=payload.data;
                if(String(data?.guru_id)!==guru||data.tanggal!==tanggal)throw Error('Acuan target tidak sesuai pilihan. Coba perbarui kembali.');
                if(!data.ready){status.textContent=data.message;return;}
                const jp=n=>n===null?'Belum diatur':format.format(n)+' JP';
                field('day',jp(data.harian.target));field('week',jp(data.mingguan.target));field('actual',jp(data.harian.aktual));field('remaining',jp(data.harian.kurang));
                if(data.working_day===false){status.textContent='Target JP hanya Senin-Jumat. Tidak ada pengisian otomatis pada '+data.hari+'. Catatan manual tetap tersedia.';return;}
                if(!data.configured){status.textContent='Target guru pada minggu ini belum diatur. Admin tetap dapat mencatat realisasi secara manual.';return;}
                const quantity=suggestedQuantity(data);
                if(!editing&&!dirty&&quantity!==null){setRange(quantity);autofilled=true;status.textContent='Sisa target hari '+data.hari+' terisi otomatis '+quantity+' JP (mulai JP 1). Periksa atau ubah rentang sesuai kegiatan, mapel, kelas dan sesi, lalu tekan Simpan.';}
                else if(editing)status.textContent='Mode koreksi: rentang JP catatan lama dipertahankan. Target hanya menjadi acuan.';
                else if(data.harian.target===0)status.textContent='Tidak ada target pada hari '+data.hari+'. Catat realisasi manual jika memang ada kegiatan mengajar.';
                else if(data.harian.kurang===0)status.textContent='Target hari terpilih sudah tercapai. Tidak ada JP tambahan yang diisi otomatis.';
                else if(data.harian.kurang>6)status.textContent='Sisa target lebih dari 6 JP. Catat per blok kegiatan/sesi; satu rentang maksimal 6 JP.';
                else status.textContent='Target menjadi acuan. Rentang yang Anda ubah sendiri tetap dipertahankan; realisasi hanya tersimpan setelah menekan Simpan.';
            }catch(error){if(version===generation){clear();status.textContent=error.name==='AbortError'?'Pembaruan target terlalu lama. Input manual tetap tersedia; coba Perbarui.':error.message+' Input manual tetap tersedia.';}}
            finally{clearTimeout(timeout);if(version===generation){panel.setAttribute('aria-busy','false');request=null;}}
        }
        teacher.addEventListener('change',()=>load(true));date.addEventListener('change',()=>load(true));refresh.addEventListener('click',()=>load());
        load();return {load,stop(){generation++;request?.abort();}};
    }
    return {suggestedQuantity,mount};
});
