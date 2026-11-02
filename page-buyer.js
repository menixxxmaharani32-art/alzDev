// Halaman pembeli: judul, HTML, dan script klien
module.exports = {
  title: 'Alzz Pedia',
  body: `
<div class="brand"><span class="logo">A</span><span>Alzz Pedia</span></div>
<h1 id="ttl">Masuk</h1>
<p class="muted" id="sub"></p>

<section class="box hide" id="auth">
<form id="af" autocomplete="on">
<label for="a_user">Username</label><input id="a_user" autocapitalize="none" autocomplete="username" required>
<label for="a_pass">Password</label><input id="a_pass" type="password" autocomplete="current-password" required>
<label class="remember" id="a_rm"><input type="checkbox" id="a_rem"><span>Ingat saya</span></label>
<div id="a_p2" class="hide"><label for="a_pass2">Ulangi password</label><input id="a_pass2" type="password" autocomplete="new-password"></div>
<p class="muted" id="a_hint" style="margin-top:8px;font-size:.88rem"></p>
<div class="err" id="a_msg" role="alert"></div>
<div id="a_ok" role="status" style="color:var(--accent);font-weight:600;font-size:.92rem"></div>
<button type="submit" id="a_go" style="width:100%">Masuk</button></form>
<p class="muted" style="margin-top:18px;text-align:center"><span id="a_swt"></span> <a href="#" id="a_swl" style="color:var(--accent);font-weight:700"></a></p></section>

<div id="app" class="hide">
<section class="box" id="who"><div class="top"><div><b id="uname"></b><p class="muted" id="ubal"></p></div>
<button class="ghost" id="bout" type="button">Keluar</button></div>
<p class="muted" style="margin-top:10px">Isi saldo lewat menu Deposit.</p></section>

<nav class="tabs" id="mtabs">
<button class="tab" type="button" data-t="sv" aria-selected="true">Layanan</button>
<button class="tab" type="button" data-t="rw" aria-selected="false">Riwayat</button>
<button class="tab" type="button" data-t="dp" aria-selected="false">Deposit</button></nav>

<div data-p="sv">
<section class="box hide" id="recbox"><h2>Rekomendasi <span class="badge">Pilihan admin</span></h2><div id="reclist"></div></section>
<section class="box"><label style="margin-top:0">Platform</label><div class="chips" id="plats"></div>
<label for="cat">Kategori</label><select id="cat" disabled></select></section>

<section class="box"><h2>Layanan</h2><p class="muted" id="cmsg" style="margin-top:6px">Memuat layanan…</p><div id="svs"></div></section>

<form class="box hide" id="bf" autocomplete="off"><h2 id="bname"></h2><p class="muted" id="binfo" style="margin-top:6px"></p><div id="stats"></div>
<label for="b_link">Link target</label><input id="b_link" inputmode="url" autocapitalize="none" placeholder="https://">
<label for="b_qty">Jumlah</label><input id="b_qty" inputmode="numeric">
<p class="muted" style="margin-top:18px">Total bayar</p><div class="total" id="total">Rp 0</div>
<div class="err" id="bmsg" role="status"></div><button type="submit" id="bgo" style="width:100%">Pesan</button></form>

</div>

<div data-p="rw" class="hide">
<section class="box" id="ords"><div class="top"><h2>Pesanan saya</h2><button class="ghost" id="orf" type="button">Segarkan</button></div><div id="olist"></div></section>
</div>

<div data-p="dp" class="hide">
<div id="dv_list">
<section class="box"><h2>Cara deposit</h2>
<ol class="steps"><li><b>1</b><span>Ajukan deposit</span></li><li><b>2</b><span>Pilih metode pembayaran</span></li><li><b>3</b><span>Upload bukti tf</span></li></ol></section>
<form class="box" id="df" autocomplete="off"><h2>Ajukan deposit</h2>
<label for="d_amt">Jumlah deposit (Rp)</label><input id="d_amt" inputmode="numeric" placeholder="Minimal 1000">
<div class="err" id="dmsg" role="status"></div><button type="submit" id="dgo" style="width:100%">Ajukan deposit</button></form>
<section class="box"><h2>Riwayat deposit</h2><div id="dlist" class="muted" style="margin-top:6px"></div></section>
</div>
<div id="dv_pay" class="hide">
<button type="button" class="ghost" id="dback">&larr; Kembali</button>
<section class="box"><h2>Pembayaran</h2>
<label>Pilih metode pembayaran</label><div class="methods" id="methods"></div><div id="pay"></div></section>
<form class="box" id="pf" autocomplete="off"><h2>Upload bukti transfer</h2>
<div class="due"><span class="muted">Jumlah yang harus dibayar</span><b id="dpay_amt"></b></div>
<label class="drop"><input id="d_proof" type="file" accept="image/*"><span id="d_fn">Pilih foto bukti transfer</span></label>
<div class="err" id="pmsg" role="status"></div><button type="submit" id="pgo" style="width:100%">Kirim bukti transfer</button></form>
</div>
</div>
</div>
<p class="help hide" id="help">Butuh bantuan? <a id="help_a" href="#" target="_blank" rel="noopener">Chat Alzz Pedia</a></p>`,
  script: `
const $=s=>document.querySelector(s);
const rp=n=>'Rp '+Math.round(Number(n)||0).toLocaleString('id-ID',{maximumFractionDigits:0});
const post=(p,b)=>fetch(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}).then(r=>r.json());
const note=(el,k,t)=>{el.style.color=k==='ok'?'var(--ok)':k==='info'?'var(--muted)':'';el.textContent=t};
let SV=[],sel=null,tot=0,me=null,loaded=false,mode='in';

/* ---------- Masuk / Daftar ---------- */
function head(){
 $('#ttl').textContent=me?'Pilih layanan':(mode==='in'?'Masuk':'Daftar');
 $('#sub').textContent=me?'Pilih platform, kategori, lalu layanan yang Anda butuhkan.':(mode==='in'?'Masuk untuk melihat layanan dan memesan.':'Buat akun baru untuk mulai memesan.')}
function setMode(m){mode=m;
 $('#a_go').textContent=m==='in'?'Masuk':'Daftar';
 $('#a_pass').autocomplete=m==='in'?'current-password':'new-password';
 $('#a_hint').textContent=m==='in'?'':'Username 3-20 karakter (huruf kecil, angka, atau garis bawah). Password minimal 6 karakter.';
 $('#a_swt').textContent=m==='in'?'Belum punya akun?':'Sudah punya akun?';
 $('#a_swl').textContent=m==='in'?'Daftar':'Masuk';
 $('#a_p2').classList.toggle('hide',m==='in');$('#a_rm').classList.toggle('hide',m==='up');$('#a_pass2').required=(m==='up');$('#a_pass2').value='';
 $('#auth').classList.toggle('up',m==='up');
 $('#a_msg').textContent='';$('#a_ok').textContent='';head()}
$('#a_swl').onclick=e=>{e.preventDefault();setMode(mode==='in'?'up':'in')};
$('#af').onsubmit=async e=>{e.preventDefault();if(mode==='up'&&$('#a_pass').value!==$('#a_pass2').value){$('#a_ok').textContent='';$('#a_msg').textContent='Password dan ulangi password tidak sama.';$('#a_pass2').focus();return}const go=$('#a_go');go.disabled=true;$('#a_msg').textContent='';$('#a_ok').textContent='';
 try{const d=await post('/api/buyer/'+(mode==='in'?'login':'register'),{username:$('#a_user').value,password:$('#a_pass').value,remember:$('#a_rem').checked});
  if(!d.status)$('#a_msg').textContent=d.error||'Gagal.';
  else if(mode==='up'){$('#a_pass').value='';setMode('in');$('#a_ok').textContent='Akun berhasil terdaftar. Silakan login.';$('#a_pass').focus()}
  else{$('#a_pass').value='';await loadMe()}}
 catch{$('#a_msg').textContent='Tidak bisa terhubung ke server.'}
 go.disabled=false};

function setMe(u){me=u;
 $('#auth').classList.toggle('hide',!!u);$('#app').classList.toggle('hide',!u);head();
 if(u){$('#uname').textContent=u.username;$('#ubal').textContent='Saldo '+rp(u.balance);if(!loaded)loadCatalog()}}
async function loadMe(){try{const r=await fetch('/api/buyer/me');const d=await r.json();setMe(d.status?d:null)}catch{setMe(null)}}
$('#bout').onclick=async()=>{try{await post('/api/buyer/logout')}catch{}location.reload()};

/* ---------- Tab & Deposit ---------- */
function tab(t){document.querySelectorAll('#app [data-p]').forEach(e=>e.classList.toggle('hide',e.dataset.p!==t));
 document.querySelectorAll('#mtabs .tab').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.t===t)));
 if(t==='dp'){dview('list');loadDeposits();refreshBal()}
 if(t==='rw')loadOrders()}
document.querySelectorAll('#mtabs .tab').forEach(b=>b.onclick=()=>tab(b.dataset.t));
async function refreshBal(){try{const r=await fetch('/api/buyer/me');const d=await r.json();if(d.status)$('#ubal').textContent='Saldo '+rp(d.balance)}catch{}}
function img2url(file,max,q){return new Promise((ok,no)=>{const u=URL.createObjectURL(file);const im=new Image();
 im.onload=()=>{const k=Math.min(1,max/Math.max(im.width,im.height));const c=document.createElement('canvas');c.width=Math.round(im.width*k);c.height=Math.round(im.height*k);
  const x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.drawImage(im,0,0,c.width,c.height);URL.revokeObjectURL(u);ok(c.toDataURL('image/jpeg',q))};
 im.onerror=()=>{URL.revokeObjectURL(u);no(new Error('Gambar tidak bisa dibaca.'))};im.src=u})}
function qrisExt(u){const e=u.slice(11,u.indexOf(';'));return e==='jpeg'?'jpg':(e||'png')}
function openQris(u){
 const ov=document.createElement('div');ov.className='lb';ov.setAttribute('role','dialog');ov.setAttribute('aria-modal','true');ov.setAttribute('aria-label','QRIS');
 const card=document.createElement('div');card.className='lb-card';
 const t=document.createElement('b');t.textContent='Scan QRIS untuk deposit';
 const i=document.createElement('img');i.src=u;i.alt='QRIS';
 const act=document.createElement('div');act.className='lb-act';
 const dl=document.createElement('a');dl.className='btn';dl.href=u;dl.download='qris.'+qrisExt(u);dl.textContent='Download QRIS';
 const cl=document.createElement('button');cl.type='button';cl.className='ghost';cl.textContent='Tutup';
 const key=e=>{if(e.key==='Escape')close()};
 function close(){ov.remove();document.body.style.overflow='';document.removeEventListener('keydown',key)}
 cl.onclick=close;ov.onclick=e=>{if(e.target===ov)close()};document.addEventListener('keydown',key);
 act.append(dl,cl);card.append(t,i,act);ov.append(card);document.body.append(ov);document.body.style.overflow='hidden';cl.focus()}
let curDep=null,payInfo=null,method=null;
function dview(v){$('#dv_list').classList.toggle('hide',v!=='list');$('#dv_pay').classList.toggle('hide',v!=='pay');window.scrollTo(0,0)}
async function openPay(dep){curDep=dep;method=null;$('#dpay_amt').textContent=rp(dep.amount);
 $('#pmsg').textContent='';$('#d_proof').value='';$('#d_fn').textContent='Pilih foto bukti transfer';$('#pay').textContent='';dview('pay');
 const mb=$('#methods');mb.textContent='Memuat…';
 try{const r=await fetch('/api/buyer/payment');const d=await r.json();payInfo=d;mb.textContent='';
  const ms=[];if(d.status&&d.dana)ms.push(['dana','Dana']);if(d.status&&d.qris)ms.push(['qris','QRIS']);
  if(!ms.length){mb.textContent='Metode pembayaran belum diatur admin. Hubungi admin.';return}
  ms.forEach(m=>{const b=document.createElement('button');b.type='button';b.className='chip';b.textContent=m[1];b.dataset.k=m[0];b.setAttribute('aria-pressed','false');b.onclick=()=>pickMethod(m[0]);mb.append(b)});
  if(ms.length===1)pickMethod(ms[0][0]);else{const h=document.createElement('p');h.className='muted';h.id='mhint';h.style.cssText='width:100%;font-size:.88rem';h.textContent='Pilih Dana atau QRIS.';mb.append(h)}}
 catch{mb.textContent='Gagal memuat metode pembayaran.'}}
function pickMethod(k){method=k;const h=$('#mhint');if(h)h.remove();
 document.querySelectorAll('#methods button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.k===k)));renderPay()}
function renderPay(){const box=$('#pay');box.textContent='';const d=payInfo||{};
 if(method==='qris'&&d.qris){const q=document.createElement('button');q.type='button';q.className='qris';q.setAttribute('aria-label','Perbesar QRIS');
  const i=document.createElement('img');i.src=d.qris;i.alt='QRIS';q.append(i);q.onclick=()=>openQris(d.qris);
  const h=document.createElement('p');h.className='muted qris-hint';h.textContent='Ketuk QRIS untuk memperbesar';
  const a=document.createElement('a');a.className='btn qris-dl';a.href=d.qris;a.download='qris.'+qrisExt(d.qris);a.textContent='Download QRIS';
  box.append(q,h,a)}
 if(method==='dana'&&d.dana){const dn=document.createElement('div');dn.className='dana';const p=document.createElement('p');p.className='muted';p.textContent='Dana'+(d.dana_name?' a.n. '+d.dana_name:'');
  const n=document.createElement('div');n.className='total';n.textContent=d.dana;
  const c=document.createElement('button');c.type='button';c.className='ghost';c.textContent='Salin nomor';
  c.onclick=async()=>{try{await navigator.clipboard.writeText(d.dana.replace(/[^0-9+]/g,''));c.textContent='Tersalin'}catch{c.textContent='Salin manual'}};
  dn.append(p,n,c);box.append(dn)}}
const DST={pending:['Menunggu persetujuan',''],approved:['Disetujui','var(--accent)'],rejected:['Ditolak','var(--danger)']};
async function loadDeposits(){const box=$('#dlist');
 try{const r=await fetch('/api/buyer/deposits');if(!r.ok)return;const d=await r.json();box.textContent='';
  if(!d.data||!d.data.length){box.textContent='Belum ada deposit.';return}
  d.data.forEach(x=>{const row=document.createElement('div');row.className='dep';
   const l=document.createElement('div');const v=document.createElement('p');v.className='dv';v.textContent=rp(x.amount);
   const m=document.createElement('p');m.className='muted dd';m.textContent=new Date(x.created_at).toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'});l.append(v,m);
   const s=document.createElement('span');s.className='pill '+x.status;const wait=x.status==='pending'&&!x.has_proof;s.textContent=wait?'Menunggu bukti':(DST[x.status]||[x.status])[0];
   let right=s;if(wait){const b=document.createElement('button');b.type='button';b.className='ghost';b.textContent='Lanjutkan';b.onclick=()=>openPay(x);right=document.createElement('div');right.className='dact';right.append(s,b)}
   row.append(l,right);box.append(row)})}catch{}}
$('#d_proof').addEventListener('change',()=>{const f=$('#d_proof').files[0];$('#d_fn').textContent=f?f.name:'Pilih foto bukti transfer'});
$('#df').onsubmit=async e=>{e.preventDefault();const m=$('#dmsg');m.style.color='';
 const amt=parseInt($('#d_amt').value,10)||0;
 if(amt<1000){m.textContent='Jumlah deposit minimal Rp 1.000.';return}
 const go=$('#dgo');go.disabled=true;note(m,'info','Mengajukan…');
 try{const d=await post('/api/buyer/deposit',{amount:amt});
  if(d.status){m.textContent='';$('#d_amt').value='';loadDeposits();openPay({id:d.id,amount:amt})}
  else note(m,'',d.error||'Gagal mengajukan deposit.')}
 catch{note(m,'','Tidak bisa terhubung ke server.')}
 go.disabled=false};
$('#pf').onsubmit=async e=>{e.preventDefault();const m=$('#pmsg');m.style.color='';
 const f=$('#d_proof').files[0];if(!f){m.textContent='Bukti transfer wajib diunggah.';return}
 if(!curDep)return;const go=$('#pgo');go.disabled=true;note(m,'info','Mengirim…');
 try{const d=await post('/api/buyer/deposit/'+curDep.id+'/proof',{proof:await img2url(f,1000,.7)});
  if(d.status){curDep=null;dview('list');note($('#dmsg'),'ok','Bukti terkirim. Saldo masuk setelah admin menyetujui.');loadDeposits()}
  else note(m,'',d.error||'Gagal mengirim bukti.')}
 catch{note(m,'','Gagal mengirim. Coba lagi atau gunakan foto yang lebih kecil.')}
 go.disabled=false};
$('#dback').onclick=()=>{dview('list');loadDeposits()};

/* ---------- Katalog (hanya setelah masuk) ---------- */
const plat=c=>{const w=(c||'').trim().split(' ')[0]||'Lainnya';return w.charAt(0).toUpperCase()+w.slice(1).toLowerCase()};
function showPlats(){const cnt={};SV.forEach(x=>{const p=plat(x.category);cnt[p]=(cnt[p]||0)+1});
 const box=$('#plats');box.textContent='';const names=Object.keys(cnt).sort((a,b)=>cnt[b]-cnt[a]);
 names.forEach(p=>{const b=document.createElement('button');b.type='button';b.className='chip';b.textContent=p;b.setAttribute('aria-pressed','false');b.onclick=()=>pickPlat(p);box.append(b)});
 if(names.length)pickPlat(names[0])}
function pickPlat(p){document.querySelectorAll('.chip').forEach(c=>c.setAttribute('aria-pressed',String(c.textContent===p)));
 const cats=[...new Set(SV.filter(x=>plat(x.category)===p).map(x=>x.category))];
 const s=$('#cat');s.textContent='';cats.forEach(c=>{const o=document.createElement('option');o.value=c;o.textContent=c;s.append(o)});s.disabled=false;showSvs()}
function svcCard(x){const b=document.createElement('button');b.type='button';b.className='svc';b.setAttribute('aria-pressed','false');
  const t=document.createElement('b');t.textContent=x.name;const m=document.createElement('span');m.className='meta';
  const pr=document.createElement('span');pr.className='price';pr.textContent=rp(x.price)+' / 1000';
  const mn=document.createElement('span');mn.className='tag';mn.textContent='Min '+x.min;
  const mx=document.createElement('span');mx.className='tag';mx.textContent='Maks '+x.max;
  m.append(pr,mn,mx);b.append(t,m);
  b.onclick=()=>{document.querySelectorAll('.svc').forEach(e=>e.setAttribute('aria-pressed','false'));b.setAttribute('aria-pressed','true');choose(x)};return b}
function showRec(ids){const box=$('#reclist');box.textContent='';
 const list=ids.map(i=>SV.find(x=>x.id===i)).filter(Boolean);
 $('#recbox').classList.toggle('hide',!list.length);
 list.forEach(x=>box.append(svcCard(x)))}
function showSvs(){const c=$('#cat').value;const box=$('#svs');box.textContent='';sel=null;$('#bf').classList.add('hide');
 const list=SV.filter(x=>x.category===c).sort((a,b)=>(Number(a.price)||1e18)-(Number(b.price)||1e18));$('#cmsg').textContent=list.length+' layanan';
 list.forEach(x=>box.append(svcCard(x)))}

function choose(x){sel=x;$('#bf').classList.remove('hide');$('#bname').textContent=x.name;$('#binfo').textContent='Jumlah '+x.min+' sampai '+x.max;
 $('#b_qty').value=x.min||'';$('#bmsg').textContent='';calc();loadStats(x);$('#bf').scrollIntoView({behavior:'smooth',block:'nearest'})}
const SC=[['pending','Pending','#d97706'],['processing','Processing','#0284c7'],['success','Success','#16a34a'],['partial','Partial','#64748b'],['error','Error','#dc2626']];
function statRow(label,color,text){const r=document.createElement('div');r.className='stat-row';const p=document.createElement('span');p.className='pill';p.style.background=color;p.textContent=label;const t=document.createElement('span');t.textContent=text;r.append(p,t);return r}
function ago(t){if(!t)return'';const s=Math.max(0,(Date.now()-new Date(t).getTime())/1000);
 if(s<60)return'BARU SAJA';const m=Math.floor(s/60);if(m<60)return m+' MENIT YANG LALU';
 const h=Math.floor(m/60);if(h<24)return h+' JAM YANG LALU';return Math.floor(h/24)+' HARI YANG LALU'}
const SL={pending:'Pending',processing:'Diproses',success:'Sukses',partial:'Sebagian',error:'Error'};
function spSec(title){const s=document.createElement('div');s.className='sp-sec';const h=document.createElement('h3');h.className='sp-h';h.textContent=title;s.append(h);return s}
async function loadStats(x){const box=$('#stats');box.textContent='';
 try{const r=await fetch('/api/buyer/stats/'+encodeURIComponent(x.id));const d=await r.json();
  if(!sel||sel.id!==x.id)return;box.textContent='';
  if(!d.status){const p=document.createElement('p');p.className='muted';p.style.marginTop='12px';p.textContent=d.error||'Statistik gagal dimuat.';box.append(p);return}
  const card=document.createElement('div');card.className='sp';const tot=d.total||0;
  const s1=spSec('Rata-Rata:');const p1=document.createElement('p');p1.append('Jumlah Pesan Rata-Rata: ');const b1=document.createElement('b');b1.textContent=Number(d.avg||0).toLocaleString('id-ID');p1.append(b1);s1.append(p1);card.append(s1);
  const s2=spSec('Statistik Pesanan');
  s2.append(statRow('Total Pesanan','#6b7fd7',tot+' pesanan'));
  SC.forEach(k=>{const c=(d.counts&&d.counts[k[0]])||0;s2.append(statRow(k[1],k[2],(tot?+(c*100/tot).toFixed(2):0)+'% ('+c+' pesanan)'))});card.append(s2);
  const s3=spSec('Pesanan Terakhir');
  if(!d.last||!d.last.length){const e=document.createElement('p');e.className='muted';e.textContent='Belum ada pesanan.';s3.append(e)}
  else d.last.forEach(o=>{const it=document.createElement('div');it.className='sp-last sp-'+o.st;
    const t=ago(o.at);if(t){const a=document.createElement('p');a.className='sp-ago';a.textContent=t;it.append(a)}
    const st=document.createElement('p');st.className='sp-st';st.textContent='Status: '+(SL[o.st]||o.st);it.append(st);
    if(o.qty){const q=document.createElement('p');q.className='sp-q';q.append('Jumlah Pesanan: ');const g=document.createElement('span');g.className='tag';g.textContent=o.qty;q.append(g);it.append(q)}
    s3.append(it)});
  card.append(s3);
  box.append(card)}
 catch{}}
function calc(){if(!sel)return;const q=Number($('#b_qty').value)||0;tot=Math.ceil(q*sel.price/1000);$('#total').textContent=rp(tot);
 note($('#bmsg'),'',(q&&(q<sel.min||(sel.max&&q>sel.max)))?'Jumlah harus antara '+sel.min+' dan '+sel.max+'.':'')}
$('#cat').onchange=showSvs;$('#b_qty').oninput=calc;
async function loadCatalog(){loaded=true;$('#cmsg').textContent='Memuat layanan…';
 try{const r=await fetch('/api/catalog');if(r.status===401){loaded=false;setMe(null);return}
  const d=await r.json();SV=d.data||[];
  if(!d.status||!SV.length){$('#cmsg').textContent='Belum ada layanan.';return}
  $('#cmsg').textContent='Pilih kategori di atas.';showPlats();showRec(d.rec||[])}
 catch{loaded=false;$('#cmsg').textContent='Gagal memuat layanan.'}}

/* ---------- Pesan ---------- */
$('#bf').onsubmit=async e=>{e.preventDefault();
 if(!sel)return;const q=Number($('#b_qty').value)||0;
 if(!$('#b_link').value.trim()){note($('#bmsg'),'','Isi link target.');return}
 if(!confirm('Pesan '+q+' untuk '+rp(tot)+'? Saldo Anda akan terpotong.'))return;
 note($('#bmsg'),'info','Memproses…');
 try{const d=await post('/api/buyer/order',{service:sel.id,target:$('#b_link').value.trim(),quantity:q});
  if(d.status){note($('#bmsg'),'ok',d.queued?'Pesanan diterima dengan status Pending. Akan diproses otomatis.':'Pesanan berhasil dibuat.');$('#b_link').value='';$('#ubal').textContent='Saldo '+rp(d.balance);loadOrders()}
  else note($('#bmsg'),'',d.error||'Gagal memesan.')}
 catch{note($('#bmsg'),'','Tidak bisa terhubung ke server.')}};

function stInfo(s){const t=String(s||'').toLowerCase();
 if(!t||t.indexOf('pend')>-1||t.indexOf('wait')>-1)return['Pending','pending'];
 if(t.indexOf('part')>-1)return['Sebagian','part'];
 if(t.indexOf('succ')>-1||t.indexOf('complet')>-1||t.indexOf('sukses')>-1||t.indexOf('selesai')>-1||t.indexOf('done')>-1)return['Sukses','ok'];
 if(t.indexOf('err')>-1||t.indexOf('fail')>-1||t.indexOf('gagal')>-1||t.indexOf('cancel')>-1||t.indexOf('refund')>-1||t.indexOf('reject')>-1)return['Error','bad'];
 if(t.indexOf('proc')>-1||t.indexOf('progress')>-1||t.indexOf('run')>-1)return['Diproses','proc'];
 return[String(s),'pending']}
function setBadge(el,s){const i=stInfo(s);el.textContent=i[0];el.className='st st-'+i[1];return i[1]!=='pending'&&i[1]!=='proc'}
async function checkOrder(id,badge,btn,row){if(btn){btn.disabled=true;btn.textContent='Mengecek…'}
 let fin=false;
 try{const d=await post('/api/buyer/status',{id});
  if(d.status){fin=setBadge(badge,d.state);if(fin&&row&&['ok','part'].indexOf(stInfo(d.state)[1])>-1)addRefillBtn(row,id);if(btn){if(fin)btn.remove();else{btn.disabled=false;btn.textContent='Cek status'}}}
  else if(btn){btn.disabled=false;btn.textContent='Cek status'}}
 catch{if(btn){btn.disabled=false;btn.textContent='Cek status'}}
 return fin}
function addRefillBtn(row,id){if(row.querySelector('.rf'))return;
 const b=document.createElement('button');b.type='button';b.className='ghost rf';b.textContent='Ajukan refill';
 const m=document.createElement('p');m.className='muted';m.style.cssText='margin-top:6px;font-size:.88rem';
 b.onclick=async()=>{if(!confirm('Ajukan refill untuk pesanan ini?'))return;b.disabled=true;m.style.color='';m.textContent='Mengirim…';
  try{const d=await post('/api/buyer/refill',{id});m.style.color=d.status?'var(--ok)':'var(--danger)';m.textContent=d.status?(d.message||'Refill diajukan.'):(d.error||'Refill gagal.')}
  catch{m.style.color='var(--danger)';m.textContent='Tidak bisa terhubung ke server.'}
  b.disabled=false};
 row.append(b,m)}
let oseq=0;
async function loadOrders(){const r=await fetch('/api/buyer/orders');if(!r.ok)return;const d=await r.json();const box=$('#olist');box.textContent='';
 const mine=++oseq;
 if(!d.data||!d.data.length){box.textContent='Belum ada pesanan.';return}
 const todo=[];
 d.data.forEach(o=>{const row=document.createElement('div');row.className='ord';
  const hd=document.createElement('div');hd.className='ohead';
  const nm=document.createElement('b');nm.textContent=o.service_name||'Pesanan #'+o.id;
  const bg=document.createElement('span');const fin=setBadge(bg,o.status);hd.append(nm,bg);
  const g=document.createElement('p');g.className='pre muted';g.style.marginTop='6px';g.textContent=o.target;
  const v=document.createElement('p');v.style.cssText='font-weight:700;margin-top:6px';
  v.textContent=(o.quantity?'Jumlah '+Number(o.quantity).toLocaleString('id-ID')+' | ':'')+rp(o.price);
  row.append(hd,g,v);
  if(!fin){const c=document.createElement('button');c.type='button';c.className='ghost';c.textContent='Cek status';
   c.onclick=()=>checkOrder(o.id,bg,c,row);row.append(c);todo.push([o.id,bg,c,row])}
  else if(['ok','part'].indexOf(stInfo(o.status)[1])>-1)addRefillBtn(row,o.id)
  box.append(row)});
 for(const t of todo.slice(0,10)){if(mine!==oseq)break;await checkOrder(t[0],t[1],t[2],t[3])}}
$('#orf').onclick=loadOrders;

fetch('/api/wa').then(r=>r.json()).then(d=>{if(d&&d.status&&d.wa){$('#help_a').href='https://wa.me/'+d.wa;$('#help').classList.remove('hide')}}).catch(()=>{});
setMode(location.hash==='#daftar'?'up':'in');
loadMe();`,
};
