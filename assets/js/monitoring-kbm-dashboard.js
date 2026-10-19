(function(root,factory){
    const api=factory();
    if(typeof module==='object'&&module.exports)module.exports=api;
    else{root.KbmDashboard=api;api.mount(root.document);}
})(typeof window==='undefined'?globalThis:window,function(){
    'use strict';
    function createPoller(options){
        const timer=options.setTimeout||setTimeout,cancel=options.clearTimeout||clearTimeout;
        const controller=options.AbortController||AbortController;
        let scheduled=null,inflight=false,pending=false,stopped=false,generation=0;
        function clear(){if(scheduled!==null){cancel(scheduled);scheduled=null;}}
        function schedule(delay=12000){clear();if(!stopped&&!options.hidden())scheduled=timer(()=>{scheduled=null;refresh(false);},delay);}
        async function refresh(changed=true){
            if(stopped)return;
            if(changed)generation++;
            clear();
            if(options.hidden()){pending=true;return;}
            if(inflight){pending=true;return;}
            inflight=true;pending=false;const version=generation;const abort=new controller();
            const timeout=timer(()=>abort.abort(),10000);
            options.busy?.(true);
            try{
                const data=await options.fetchData(abort.signal);
                if(!stopped&&version===generation)options.apply(data);
            }catch(error){
                if(!stopped&&(version===generation||error.status===401||error.status===403))options.error(error);
                if(error.status===401||error.status===403){stopped=true;pending=false;clear();}
            }finally{
                cancel(timeout);inflight=false;options.busy?.(false);
                if(!stopped){if(pending&&!options.hidden())schedule(0);else schedule();}
            }
        }
        function visibility(){clear();if(!options.hidden())refresh(true);}
        return {refresh,visibility,start:()=>{stopped=false;schedule();},stop:()=>{stopped=true;clear();},state:()=>({inflight,pending,stopped})};
    }
    // Acuan server WIB + waktu berlalu monoton; jam perangkat tidak menjadi sumber waktu.
    function createServerClock(node,options={}){
        const now=options.now||(()=>performance.now()),timer=options.setTimeout||setTimeout,cancel=options.clearTimeout||clearTimeout;
        const format=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Jakarta',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'});
        let base=NaN,origin=0,scheduled=null,running=false;
        function draw(){if(Number.isFinite(base))node.textContent=format.format(new Date(base+Math.max(0,now()-origin)));}
        function tick(){scheduled=null;draw();if(running)scheduled=timer(tick,1000);}
        function stop(){running=false;if(scheduled!==null)cancel(scheduled);scheduled=null;}
        return {
            sync(value){if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value))return;const stamp=Date.parse(value.replace(' ','T')+'+07:00');if(!Number.isFinite(stamp))return;base=stamp;origin=now();draw();},
            start(){stop();running=true;tick();},stop
        };
    }
    function mount(doc){
        const configNode=doc.getElementById('kbmDashboardConfig');if(!configNode)return;
        const config=JSON.parse(configNode.textContent),form=doc.getElementById('kbmFilters'),cards=doc.getElementById('kbmCards'),notice=doc.getElementById('kbmConnection');
        const date=doc.getElementById('kbmDate'),classSelect=doc.getElementById('kbmClass');let today=config.today;
        const clockNode=doc.getElementById('kbmLiveClock'),liveClock=clockNode?createServerClock(clockNode):null;
        liveClock?.sync(config.server_time);if(!doc.hidden)liveClock?.start();
        const sessionChoices=Array.from(doc.querySelectorAll?.('[data-kbm-session]')||[]);
        const dialog=doc.getElementById('kbmClassDialog');let dialogClass=null,dialogTrigger=null;
        function updateDialog(){
            if(!dialog||!dialog.open||!dialogClass)return;
            const source=cards.querySelector('[data-class-id="'+dialogClass+'"]');
            const target=doc.getElementById('kbmDialogContent');
            if(!source){target.textContent='Kelas tidak tersedia pada filter ini. Tutup detail untuk kembali ke papan.';return;}
            const copy=source.cloneNode(true);copy.querySelector('.kbm-card-open')?.remove();
            for(const detail of copy.querySelectorAll('details'))detail.open=true;
            target.replaceChildren(copy);
        }
        if(dialog){
            cards.addEventListener('click',event=>{const trigger=event.target.closest('[data-kbm-open]');if(!trigger||!cards.contains(trigger))return;dialogClass=trigger.dataset.kbmOpen;if(!/^\d+$/.test(dialogClass))return;dialogTrigger=trigger;dialog.showModal();updateDialog();});
            doc.getElementById('kbmDialogClose').addEventListener('click',()=>dialog.close());
            dialog.addEventListener('close',()=>{const current=cards.querySelector('[data-kbm-open="'+dialogClass+'"]');(current||dialogTrigger)?.focus();dialogClass=null;});
        }
        doc.getElementById('kbmMenuToggle')?.addEventListener('click',event=>{const expanded=doc.documentElement.classList.toggle('kbm-show-menu');event.currentTarget.setAttribute('aria-expanded',String(expanded));});
        doc.getElementById('kbmFullscreen')?.addEventListener('click',async()=>{
            try{if(doc.fullscreenElement)await doc.exitFullscreen();else await doc.documentElement.requestFullscreen();}catch(error){notice.textContent='Mode layar penuh tidak tersedia. Gunakan F11 atau tetap gunakan papan monitoring ini.';notice.classList.remove('d-none');}
        });
        function updateSessionMenu(){
            for(const link of sessionChoices){const active=link.dataset.kbmSession===doc.getElementById('kbmSession').value;link.classList.toggle('active',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');}
        }
        function query(){const params=new URLSearchParams(new FormData(form));if(params.get('tanggal')===today)params.delete('tanggal');return params;}
        const poller=createPoller({hidden:()=>doc.hidden,
            async fetchData(signal){
                const response=await fetch(config.endpoint+'?'+query().toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
                if(!response.ok){const error=new Error('Monitoring tidak tersedia');error.status=response.status;throw error;}
                if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('Respons monitoring tidak valid');
                const data=await response.json();
                if(!data.ok||typeof data.html!=='string'||typeof data.server_time!=='string'||!data.summary||!Array.isArray(data.class_options))throw new Error('Data monitoring tidak valid');
                return data;
            },
            apply(data){
                const opened=Array.from(cards.querySelectorAll('details[open][data-meeting-class]'),node=>node.dataset.meetingClass);
                cards.innerHTML=data.html;
                for(const id of opened)if(/^\d+$/.test(id)){const details=cards.querySelector('details[data-meeting-class="'+id+'"]');if(details)details.open=true;}
                for(const color of ['merah','hijau','kuning'])doc.getElementById('kbmCount_'+color).textContent=data.summary[color];
                doc.getElementById('kbmIncomplete').textContent=data.summary.incomplete+' kelas perlu konfigurasi';
                doc.getElementById('kbmScope').textContent=data.shown+' dari '+data.total+' kelas · ringkasan sesuai filter';
                doc.getElementById('kbmUpdated').textContent=data.server_time+' WIB';
                liveClock?.sync(data.server_time);
                const selected=classSelect.value;classSelect.replaceChildren();
                for(const option of [{id:'',name:'Semua kelas'},...data.class_options]){const node=doc.createElement('option');node.value=String(option.id);node.textContent=option.name;classSelect.appendChild(node);}
                classSelect.value=selected;today=data.today;date.value=data.date;date.max=data.today;
                const displayDate=doc.getElementById('kbmDisplayDate');if(displayDate){const parts=data.date.split('-');const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];displayDate.textContent=Number(parts[2])+' '+months[Number(parts[1])-1]+' '+parts[0];}
                updateSessionMenu();
                updateDialog();
                notice.classList.add('d-none');notice.textContent='';
                const url=new URL(location.href);url.search=query().toString();history.replaceState(null,'',url);
            },
            error(error){
                notice.textContent=(error.status===401||error.status===403)?'Akses atau sesi login berakhir. Muat ulang halaman dan login kembali. Data terakhir belum diperbarui.':'Koneksi bermasalah. Data belum diperbarui; data terakhir tetap ditampilkan. Sistem akan mencoba lagi sekitar 12 detik.';
                notice.classList.remove('d-none');
            },busy(value){cards.setAttribute('aria-busy',String(value));}
        });
        form.addEventListener('submit',event=>{event.preventDefault();poller.refresh(true);});
        for(const link of sessionChoices)link.addEventListener('click',event=>{
            if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button>0)return;
            event.preventDefault();doc.getElementById('kbmSession').value=link.dataset.kbmSession;
            classSelect.value='';doc.getElementById('kbmStatus').value='';poller.refresh(true);
        });
        for(const field of form.querySelectorAll('select,input'))field.addEventListener('change',()=>poller.refresh(true));
        doc.getElementById('kbmRefresh').addEventListener('click',()=>poller.refresh(true));
        doc.addEventListener('visibilitychange',()=>{doc.getElementById('kbmPollingNote').textContent=doc.hidden?'Pembaruan dijeda selama tab tidak aktif.':'Data diperbarui sekitar setiap 12 detik; keterbaruan mengikuti interval dan koneksi.';if(doc.hidden)liveClock?.stop();else liveClock?.start();poller.visibility();});
        window.addEventListener('pagehide',()=>{poller.stop();liveClock?.stop();});
        window.addEventListener('pageshow',event=>{if(event.persisted){poller.start();if(!doc.hidden)liveClock?.start();poller.refresh(true);}});
        poller.start();return poller;
    }
    return {createPoller,createServerClock,mount};
});
