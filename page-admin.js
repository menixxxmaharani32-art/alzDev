// Halaman dasbor admin: judul, HTML, dan script klien
module.exports = {
  title: 'Dasbor admin',
  body: `
<div class="top"><h1>Dasbor admin</h1><button class="ghost" id="logout" type="button">Keluar</button></div>
<nav class="tabs">
<button class="tab" type="button" data-t="home" aria-selected="true">Pendapatan</button>
<button class="tab" type="button" data-t="ord" aria-selected="false">Pesanan</button>
<button class="tab" type="button" data-t="dp" id="dtab" aria-selected="false">Deposit</button>
<button class="tab" type="button" data-t="us" aria-selected="false">Pembeli</button>
<button class="tab" type="button" data-t="rec" aria-selected="false">Rekomendasi</button>
<button class="tab" type="button" data-t="pay" aria-selected="false">Pembayaran</button>
<button class="tab" type="button" data-t="set" aria-selected="false">Pengaturan</button></nav>

<div data-p="home"><div class="hm">
<section class="hero"><p class="lbl">Pendapatan bulan ini</p><div class="big" id="rmonth">…</div><p class="sub" id="rmonthsub"></p></section>
<div class="g2">
<section class="stat"><p class="lbl">Hari ini</p><div class="num" id="rtoday">…</div><p class="sub" id="rtodaysub"></p></section>
<section class="stat"><p class="lbl">Minggu ini</p><div class="num" id="rweek">…</div><p class="sub" id="rweeksub"></p></section>
<section class="stat"><p class="lbl">Total keseluruhan</p><div class="num" id="rtotal">…</div><p class="sub" id="rcount"></p></section>
<section class="stat" id="godp" role="button" tabindex="0"><p class="lbl">Menunggu konfirmasi</p><div class="num warn" id="pcount">…</div><p class="sub">deposit menunggu persetujuan</p></section>
</div>
<section class="box"><h2 class="ttl">Rincian Pendapatan</h2>
<div class="seg2" id="rseg"><button type="button" data-m="d" aria-selected="true">Harian</button><button type="button" data-m="w" aria-selected="false">Mingguan</button><button type="button" data-m="m" aria-selected="false">Bulanan</button></div>
<div class="rhead"><span class="muted" id="rrange"></span><b id="rsum"></b></div>
<div class="bars" id="rbars"></div><div id="rlist" style="margin-top:8px"></div>
<p class="muted" id="rnote" style="margin-top:10px;font-size:.85rem"></p></section>
<section class="box"><p class="lbl">Saldo Sprint Pedia</p><div class="saldo" id="saldo">…</div><p class="muted" id="provider"></p><button class="ghost" id="refresh" type="button" style="margin-top:14px">Segarkan saldo</button></section>
<section class="box"><p class="lbl">Pesanan menunggu saldo Sprint Pedia</p><div class="saldo" id="qcount">…</div><p class="muted" style="margin-top:6px;font-size:.85rem">Diproses otomatis setelah saldo Sprint Pedia terisi.</p></section>
</div></div>

<div data-p="ord" class="hide"><section class="box"><h2>Riwayat pesanan pembeli</h2>
<input id="o_q" placeholder="Cari username pembeli" autocapitalize="none" style="margin-top:12px">
<div class="amts" id="o_f"><button type="button" class="chip" data-s="all" aria-pressed="true">Semua</button><button type="button" class="chip" data-s="success" aria-pressed="false">Sukses</button><button type="button" class="chip" data-s="pending" aria-pressed="false">Menunggu</button><button type="button" class="chip" data-s="processing" aria-pressed="false">Diproses</button><button type="button" class="chip" data-s="error" aria-pressed="false">Gagal</button></div>
<div id="o_list" style="margin-top:8px"></div></section></div>

<div data-p="dp" class="hide">
<section class="box"><h2>Menunggu persetujuan</h2><div id="dpend" style="margin-top:8px"></div></section>
<section class="box"><h2>Riwayat terbaru</h2><div id="ddone" style="margin-top:8px"></div></section></div>

<div data-p="us" class="hide">
<form class="box" id="tform" autocomplete="off"><h2>Tambah saldo pembeli</h2>
<label for="t_user">Username pembeli</label><input id="t_user" autocapitalize="none" required>
<label for="t_amt">Jumlah (Rp, boleh minus untuk mengurangi)</label><input id="t_amt" inputmode="numeric" required>
<div class="err" id="tmsg" role="status"></div><button type="submit">Simpan saldo</button></form>
<section class="box"><h2>Daftar pembeli</h2>
<input id="uq" placeholder="Cari username" style="margin-top:12px" autocomplete="off">
<div id="ulist" style="margin-top:8px"></div></section></div>

<div data-p="rec" class="hide">
<section class="box"><h2>Layanan rekomendasi</h2>
<p class="muted" style="margin-top:6px">Tampil paling atas di halaman pembeli. Maksimal 10 layanan.</p>
<div id="reclist" style="margin-top:8px"></div><div class="err" id="recmsg" role="status"></div></section>
<section class="box"><h2>Tambah layanan</h2>
<input id="rec_q" placeholder="Cari nama layanan atau kategori" style="margin-top:12px" autocomplete="off">
<div id="recres" style="margin-top:8px"></div></section></div>

<div data-p="pay" class="hide">
<form class="box" id="payf" autocomplete="off"><h2>Pengaturan pembayaran</h2>
<p class="muted" style="margin-top:6px">Ditampilkan ke pembeli di menu Deposit.</p>
<label for="p_dana">Nomor Dana</label><input id="p_dana" inputmode="tel" placeholder="08xxxxxxxxxx">
<label for="p_name">Atas nama</label><input id="p_name">
<label for="p_qris">Foto QRIS</label><input id="p_qris" type="file" accept="image/*">
<div id="p_prev" style="margin-top:12px"></div>
<button type="button" class="ghost hide" id="p_del">Hapus foto QRIS</button>
<div class="err" id="p_msg" role="status"></div><button type="submit" style="width:100%">Simpan pembayaran</button></form></div>

<div data-p="set" class="hide">
<form class="box" id="form" autocomplete="off"><h2>Key Sprint Pedia</h2>
<p class="muted" style="margin-top:6px">Kosongkan kolom yang tidak ingin diubah.</p>
<label for="secret_key">Secret key</label><input id="secret_key" type="password" autocomplete="off">
<label for="api_key">API key</label><input id="api_key" type="password" autocomplete="off">
<label for="markup">Markup harga untuk pembeli (%)</label><input id="markup" inputmode="decimal">
<div class="err" id="msg" role="status"></div><button type="submit">Simpan pengaturan</button></form>
<form class="box" id="waf" autocomplete="off"><h2>Kontak bantuan WhatsApp</h2>
<p class="muted" style="margin-top:6px">Tampil di halaman pembeli sebagai &ldquo;Butuh bantuan? Chat Alzz Pedia&rdquo;. Kosongkan lalu simpan untuk menyembunyikan.</p>
<label for="wa_no">Nomor WhatsApp</label><input id="wa_no" inputmode="tel" placeholder="08xxxxxxxxxx">
<div class="err" id="wa_msg" role="status"></div><button type="submit">Simpan nomor</button></form>
<section class="box"><h2>Notifikasi HP</h2>
<p class="muted" style="margin-top:6px">Dapat pemberitahuan di layar HP saat ada pesanan baru, deposit baru, atau pembeli baru mendaftar, walau browser ditutup. Di iPhone, buka dasbor dari ikon &ldquo;Tambah ke Layar Utama&rdquo; dulu.</p>
<p id="pn_st" style="margin-top:10px;font-weight:600"></p>
<div class="row" style="margin-top:10px"><button type="button" id="pn_on">Aktifkan notifikasi</button><button type="button" class="ghost hide" id="pn_test">Tes notifikasi</button></div>
<div class="err" id="pn_msg" role="status"></div></section>
<section class="box"><h2>Layanan untuk pembeli</h2>
<p class="muted" style="margin-top:6px">Impor daftar layanan dari Sprint Pedia agar tampil di halaman pembeli.</p>
<button type="button" id="loadsv">Impor semua layanan</button><div class="err" id="svmsg" role="status"></div></section></div>`,
  script: `
const $=s=>document.querySelector(s);
const rp=n=>'Rp '+Math.round(Number(n)||0).toLocaleString('id-ID',{maximumFractionDigits:0});
async function api(p,o){const r=await fetch(p,o);if(r.status===401){location.href='/login';throw new Error('401')}return r.json()}
const post=(p,b)=>api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})});
const errOf=d=>typeof d.data==='string'?d.data:(d.error||'Terjadi kesalahan.');
const when=s=>new Date(s).toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'});
const say=(el,ok,t)=>{el.style.color=ok?'var(--accent)':'';el.textContent=t};
const btn=(t,cls,fn)=>{const b=document.createElement('button');b.type='button';b.className=cls||'';b.textContent=t;b.onclick=fn;return b};
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload()});

let ofil='all',otm=null;
const OG={success:['Sukses','approved'],pending:['Menunggu',''],processing:['Diproses',''],partial:['Sebagian',''],error:['Gagal','rejected']};
async function loadOrd(){const box=$('#o_list');
 try{const d=await api('/api/admin/orders?status='+ofil+'&q='+encodeURIComponent($('#o_q').value.trim()));box.textContent='';
  if(!d.status){box.textContent=errOf(d);return}
  if(!d.data.length){box.textContent='Belum ada pesanan.';return}
  d.data.forEach(o=>{const r=document.createElement('div');r.className='orow';
   const h=document.createElement('div');h.className='ohd';const n=document.createElement('b');n.textContent=o.service_name||'Pesanan #'+o.id;
   const g=OG[o.grp]||[o.grp,''];const s=document.createElement('span');s.className='pill '+g[1];s.textContent=g[0];h.append(n,s);
   const m=document.createElement('p');m.className='muted';m.textContent=o.username+(o.created_at?' · '+new Date(o.created_at).toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'}):'');
   const t=document.createElement('p');t.className='tg muted';t.textContent=o.target||'';
   const v=document.createElement('p');v.className='ov';v.textContent=(o.quantity?'Jumlah '+Number(o.quantity).toLocaleString('id-ID')+' | ':'')+rp(o.price);
   r.append(h,m,t,v);box.append(r)})}catch{}}
document.querySelectorAll('#o_f .chip').forEach(b=>b.onclick=()=>{ofil=b.dataset.s;document.querySelectorAll('#o_f .chip').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));loadOrd()});
$('#o_q').addEventListener('input',()=>{clearTimeout(otm);otm=setTimeout(loadOrd,350)});
function tab(t){document.querySelectorAll('[data-p]').forEach(e=>e.classList.toggle('hide',e.dataset.p!==t));
 document.querySelectorAll('.tab').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.t===t)));
 if(t==='ord')loadOrd();if(t==='dp')loadDeposits();if(t==='us')loadUsers();if(t==='rec')loadRec()}
document.querySelectorAll('.tab').forEach(b=>b.onclick=()=>tab(b.dataset.t));
$('#godp').onclick=()=>tab('dp');
function item(t,sub,fn){const b=document.createElement('button');b.type='button';b.className='item';
 const a=document.createElement('b');a.textContent=t;const c=document.createElement('span');c.className='muted';c.textContent=sub;b.append(a,c);b.onclick=fn;return b}
function img2url(file,max,q){return new Promise((ok,no)=>{const u=URL.createObjectURL(file);const im=new Image();
 im.onload=()=>{const k=Math.min(1,max/Math.max(im.width,im.height));const c=document.createElement('canvas');c.width=Math.round(im.width*k);c.height=Math.round(im.height*k);
  const x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.drawImage(im,0,0,c.width,c.height);URL.revokeObjectURL(u);ok(c.toDataURL('image/jpeg',q))};
 im.onerror=()=>{URL.revokeObjectURL(u);no(new Error('Gambar tidak bisa dibaca.'))};im.src=u})}

/* ---------- Ringkasan & pengaturan ---------- */
async function balance(){$('#provider').textContent='Mengambil saldo…';
 try{const d=await api('/api/profile');
  if(d.status){$('#saldo').textContent=rp(d.data.balance);$('#provider').textContent='Terhubung sebagai '+d.data.username}
  else{$('#saldo').textContent='—';$('#provider').textContent=errOf(d)}}catch{}}
let RV=null,rmode='d';
const D1=864e5,NL=String.fromCharCode(10),ymd=t=>new Date(t).toISOString().slice(0,10),pd=s=>Date.parse(s+'T00:00:00Z');
const MON=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],HR=['Min','Sen','Sel','Rab','Kam','Jum','Sab'],HRF=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const kk=n=>n>=1e6?(n/1e6).toFixed(n%1e6?1:0).replace('.',',')+' jt':n>=1e3?Math.round(n/1e3)+' rb':String(n);
function big(el,n){el.textContent='';const s=document.createElement('small');s.textContent='Rp';el.append(s,' '+Math.round(Number(n)||0).toLocaleString('id-ID'))}
function sumR(a,b){let s=0,c=0;for(let t=pd(a);t<=pd(b);t+=D1){const e=RV.daily[ymd(t)];if(e){s+=e[0];c+=e[1]}}return[s,c]}
function periods(){const t0=pd(RV.now),out=[];
 if(rmode==='d')for(let i=0;i<14;i++){const t=t0-i*D1,s=ymd(t),d=new Date(t),r=sumR(s,s);out.push({v:r[0],c:r[1],sh:String(d.getUTCDate()),sh2:HR[d.getUTCDay()],lbl:HRF[d.getUTCDay()]+', '+d.getUTCDate()+' '+MON[d.getUTCMonth()]})}
 else if(rmode==='w'){const dow=(new Date(t0).getUTCDay()+6)%7;for(let i=0;i<8;i++){const st=t0-dow*D1-i*7*D1,en=st+6*D1,r=sumR(ymd(st),ymd(en)),a=new Date(st),b=new Date(en);out.push({v:r[0],c:r[1],sh:String(a.getUTCDate()),sh2:MON[a.getUTCMonth()],lbl:a.getUTCDate()+' '+MON[a.getUTCMonth()]+' - '+b.getUTCDate()+' '+MON[b.getUTCMonth()]})}}
 else{const n=new Date(t0);for(let i=0;i<6;i++){const st=Date.UTC(n.getUTCFullYear(),n.getUTCMonth()-i,1),en=Date.UTC(n.getUTCFullYear(),n.getUTCMonth()-i+1,0),r=sumR(ymd(st),ymd(en)),d=new Date(st);out.push({v:r[0],c:r[1],sh:MON[d.getUTCMonth()],sh2:String(d.getUTCFullYear()).slice(2),lbl:MON[d.getUTCMonth()]+' '+d.getUTCFullYear()})}}
 return out}
function drawRev(){if(!RV||!RV.has_date)return;const P=periods(),R=P.slice().reverse(),max=Math.max(1,...P.map(x=>x.v));
 $('#rrange').textContent=rmode==='d'?'14 hari terakhir':rmode==='w'?'8 minggu terakhir':'6 bulan terakhir';
 $('#rsum').textContent=rp(P.reduce((a,x)=>a+x.v,0))+' · '+P.reduce((a,x)=>a+x.c,0)+' pesanan';
 const bx=$('#rbars');bx.textContent='';bx.style.gridTemplateColumns='repeat('+R.length+',1fr)';
 R.forEach(x=>{const c=document.createElement('div');c.className='bc';const t=document.createElement('span');t.className='bv';t.textContent=x.v?kk(x.v):'';
  const b=document.createElement('i');b.style.height=Math.max(3,Math.round(x.v/max*110))+'px';if(!x.v)b.className='z';
  const l=document.createElement('b');l.textContent=x.sh;const l2=document.createElement('small');l2.textContent=x.sh2;c.append(t,b,l,l2);bx.append(c)});
 const ls=$('#rlist');ls.textContent='';
 P.forEach((x,i)=>{const r=document.createElement('div');r.className='rr'+(i===0?' now':'');const a=document.createElement('div'),t=document.createElement('b');t.textContent=x.lbl;
  if(i===0){const g=document.createElement('em');g.textContent=rmode==='d'?'Hari ini':rmode==='w'?'Minggu ini':'Bulan ini';t.append(' ',g)}
  const s=document.createElement('p');s.className='muted';s.textContent=x.c+' pesanan';a.append(t,s);
  const v=document.createElement('span');v.className=x.v?'pos':'muted';v.textContent=rp(x.v);r.append(a,v);ls.append(r)})}
document.querySelectorAll('#rseg button').forEach(b=>b.onclick=()=>{rmode=b.dataset.m;document.querySelectorAll('#rseg button').forEach(x=>x.setAttribute('aria-selected',String(x===b)));drawRev()});
async function loadRevenue(){
 try{const d=await api('/api/admin/revenue');
  if(!d.status){$('#rnote').textContent=errOf(d);return}
  RV=d;big($('#rtotal'),d.total);$('#rcount').textContent=Number(d.orders).toLocaleString('id-ID')+' pesanan';
  if(!d.has_date){$('#rnote').textContent='Data tanggal pesanan belum tersedia, hanya total yang ditampilkan.';return}
  const t0=pd(d.now),wk=t0-((new Date(t0).getUTCDay()+6)%7)*D1,n=new Date(t0),Y=n.getUTCFullYear(),M=n.getUTCMonth();
  const m=sumR(ymd(Date.UTC(Y,M,1)),d.now),pm=sumR(ymd(Date.UTC(Y,M-1,1)),ymd(Date.UTC(Y,M,0))),td=sumR(d.now,d.now),yd=sumR(ymd(t0-D1),ymd(t0-D1)),w=sumR(ymd(wk),d.now),pw=sumR(ymd(wk-7*D1),ymd(wk-D1));
  big($('#rmonth'),m[0]);$('#rmonthsub').textContent=m[1]+' pesanan · Bulan lalu '+rp(pm[0]);
  big($('#rtoday'),td[0]);$('#rtodaysub').textContent=td[1]+' pesanan'+NL+'Kemarin '+rp(yd[0]);
  big($('#rweek'),w[0]);$('#rweeksub').textContent=w[1]+' pesanan'+NL+'Minggu lalu '+rp(pw[0]);
  $('#rnote').textContent='Dihitung dari untung bersih (harga jual dikurangi modal Sprint Pedia). Hanya pesanan yang sukses (waktu WIB).';drawRev()}
 catch{}}
function syncAll(){return post('/api/admin/orders/sync').then(d=>{if(d&&d.status)$('#qcount').textContent=d.queued||0;loadRevenue()}).catch(()=>{})}
$('#refresh').onclick=()=>{balance();syncAll();loadRevenue()};
async function init(){const d=await api('/api/settings');
 $('#secret_key').placeholder=d.secret_key||'Belum diisi';$('#api_key').placeholder=d.api_key||'Belum diisi';$('#markup').value=d.markup_percent||'0';
 if(d.secret_key&&d.api_key)balance();else{$('#saldo').textContent='—';$('#provider').textContent='Isi key di tab Pengaturan.'}}
$('#form').onsubmit=async e=>{e.preventDefault();
 const d=await post('/api/settings',{secret_key:$('#secret_key').value.trim(),api_key:$('#api_key').value.trim(),markup_percent:$('#markup').value.trim()});
 say($('#msg'),d.ok,d.ok?'Pengaturan tersimpan.':errOf(d));
 if(d.ok){$('#secret_key').value='';$('#api_key').value='';init()}};
async function loadWa(){try{const d=await api('/api/admin/wa');if(d.status)$('#wa_no').value=d.wa||''}catch{}}
$('#waf').onsubmit=async e=>{e.preventDefault();
 const d=await post('/api/admin/wa',{wa:$('#wa_no').value.trim()});
 say($('#wa_msg'),d.status,d.status?(d.wa?'Tersimpan. Pembeli bisa chat ke +'+d.wa+'.':'Nomor dihapus. Tombol bantuan disembunyikan.'):errOf(d));
 if(d.status)$('#wa_no').value=d.wa||''};
$('#loadsv').onclick=async()=>{const m=$('#svmsg');say(m,true,'Mengimpor semua layanan, tunggu sebentar…');
 try{const d=await post('/api/services/sync');d.status?say(m,true,d.count+' layanan berhasil diimpor.'):say(m,false,errOf(d))}catch{}};

/* ---------- Deposit ---------- */
function depRow(x,pending){const row=document.createElement('div');row.className='ord';
 const a=document.createElement('b');a.textContent=x.username;
 const v=document.createElement('p');v.style.fontWeight='700';v.textContent=rp(x.amount);
 const m=document.createElement('p');m.className='muted';m.textContent=when(x.created_at);row.append(a,v,m);
 if(!pending){const s=document.createElement('p');s.style.fontWeight='600';s.style.color=x.status==='approved'?'var(--accent)':'var(--danger)';
  s.textContent=x.status==='approved'?'Disetujui':'Ditolak';row.append(s);return row}
 const bar=document.createElement('div');bar.className='row';
 if(x.has_proof){const pb=btn('Lihat bukti','ghost',async()=>{
  const old=row.querySelector('img');if(old){old.remove();pb.textContent='Lihat bukti';return}
  pb.disabled=true;try{const d=await api('/api/admin/deposits/'+x.id+'/proof');
   if(d.proof){const i=document.createElement('img');i.src=d.proof;i.alt='Bukti transfer';i.style.cssText='max-width:100%;border-radius:12px;margin-top:12px;display:block';row.append(i);pb.textContent='Sembunyikan bukti'}
   else alert(errOf(d))}catch{}pb.disabled=false});bar.append(pb)}
 const ok=btn('Setujui','',async()=>{
  if(!confirm('Setujui deposit '+rp(x.amount)+' untuk '+x.username+'? Saldo langsung masuk ke akun pembeli.'))return;
  ok.disabled=no.disabled=true;
  try{const d=await post('/api/admin/deposits/'+x.id+'/approve');if(!d.status)alert(errOf(d));loadDeposits();loadUsers()}catch{ok.disabled=no.disabled=false}});
 const no=btn('Tolak','ghost',async()=>{
  if(!confirm('Tolak deposit '+rp(x.amount)+' dari '+x.username+'?'))return;
  ok.disabled=no.disabled=true;
  try{const d=await post('/api/admin/deposits/'+x.id+'/reject');if(!d.status)alert(errOf(d));loadDeposits()}catch{ok.disabled=no.disabled=false}});
 bar.append(ok,no);row.append(bar);return row}
async function loadDeposits(){
 try{const [p,h]=await Promise.all([api('/api/admin/deposits'),api('/api/admin/deposits?status=done')]);
  const pe=$('#dpend'),dn=$('#ddone');pe.textContent='';dn.textContent='';
  const n=p.data?p.data.length:0;$('#pcount').textContent=n;$('#dtab').textContent=n?'Deposit ('+n+')':'Deposit';
  if(!p.status)pe.textContent=errOf(p);else if(!n)pe.textContent='Tidak ada deposit yang menunggu.';else p.data.forEach(x=>pe.append(depRow(x,true)));
  if(!h.status)dn.textContent=errOf(h);else if(!h.data.length)dn.textContent='Belum ada riwayat.';else h.data.forEach(x=>dn.append(depRow(x,false)))}catch{}}

/* ---------- Pembeli ---------- */
async function loadUsers(){const d=await api('/api/admin/users?q='+encodeURIComponent($('#uq').value.trim()));const box=$('#ulist');box.textContent='';
 if(!d.status){box.textContent=errOf(d);return}
 if(!d.data.length){box.textContent='Belum ada pembeli.';return}
 d.data.forEach(u=>{const row=document.createElement('div');row.style.cssText='display:flex;align-items:center;gap:8px;border-top:1px solid var(--line)';
  const it=item(u.username,'Saldo '+rp(u.balance),()=>{$('#t_user').value=u.username;$('#t_amt').focus()});it.style.cssText='flex:1;min-width:0;border-top:0';
  const del=document.createElement('button');del.type='button';del.className='ghost';del.textContent='Hapus';del.style.cssText='width:auto;padding:8px 12px;color:var(--danger);border-color:var(--danger)';
  del.onclick=async()=>{if(!confirm('Hapus pembeli '+u.username+'? Saldo, riwayat pesanan, dan riwayat deposit pembeli ini ikut terhapus. Tidak bisa dibatalkan.'))return;
   del.disabled=true;try{const d=await post('/api/admin/users/delete',{username:u.username});if(d.status)loadUsers();else{alert(errOf(d));del.disabled=false}}catch{del.disabled=false}};
  row.append(it,del);box.append(row)})}
let tm;$('#uq').oninput=()=>{clearTimeout(tm);tm=setTimeout(loadUsers,300)};
$('#tform').onsubmit=async e=>{e.preventDefault();
 const d=await post('/api/admin/topup',{username:$('#t_user').value.trim(),amount:$('#t_amt').value.trim()});
 say($('#tmsg'),d.status,d.status?('Berhasil. Saldo sekarang '+rp(d.balance)):errOf(d));
 if(d.status){$('#t_amt').value='';loadUsers()}};

/* ---------- Rekomendasi ---------- */
let REC=[];
function drawRec(){const box=$('#reclist');box.textContent='';
 if(!REC.length){box.textContent='Belum ada layanan rekomendasi.';return}
 REC.forEach((x,i)=>{const row=document.createElement('div');row.className='ord';
  const n=document.createElement('b');n.textContent=x.name;
  const m=document.createElement('p');m.className='muted';m.textContent=x.category+' | '+rp(x.price)+' / 1000';
  const bar=document.createElement('div');bar.className='row';
  bar.append(btn('Naik','ghost',()=>moveRec(i,-1)),btn('Turun','ghost',()=>moveRec(i,1)),btn('Hapus','ghost',()=>{REC.splice(i,1);saveRec()}));
  row.append(n,m,bar);box.append(row)})}
function moveRec(i,d){const j=i+d;if(j<0||j>=REC.length)return;const t=REC[i];REC[i]=REC[j];REC[j]=t;saveRec()}
async function loadRec(){try{const d=await api('/api/admin/recommended');if(d.status){REC=d.data;drawRec()}else $('#reclist').textContent=errOf(d)}catch{}}
async function saveRec(){try{const d=await post('/api/admin/recommended',{ids:REC.map(x=>x.id)});
  say($('#recmsg'),d.status,d.status?'Tersimpan. Langsung tampil di halaman pembeli.':errOf(d));await loadRec();searchRec()}catch{}}
async function searchRec(){const q=$('#rec_q').value.trim();const box=$('#recres');box.textContent='';if(q.length<2)return;
 try{const d=await api('/api/admin/services/search?q='+encodeURIComponent(q));
  if(!d.status){box.textContent=errOf(d);return}
  if(!d.data.length){box.textContent='Layanan tidak ditemukan.';return}
  d.data.forEach(x=>{const has=REC.some(r=>r.id===x.id);
   const it=item(x.name,(has?'Sudah ditambahkan | ':'')+x.category+' | '+rp(x.price)+' / 1000',()=>{
    if(has)return;if(REC.length>=10){say($('#recmsg'),false,'Maksimal 10 layanan rekomendasi.');return}
    REC.push(x);saveRec()});
   if(has)it.style.opacity='.55';box.append(it)})}catch{}}
let rtm;$('#rec_q').oninput=()=>{clearTimeout(rtm);rtm=setTimeout(searchRec,350)};

/* ---------- Pembayaran (QRIS & Dana) ---------- */
let newQris='';
function showQris(u){const b=$('#p_prev');b.textContent='';if(!u)return;
 const i=document.createElement('img');i.src=u;i.alt='QRIS';i.style.cssText='max-width:100%;border-radius:12px;background:#fff';b.append(i)}
async function loadPay(){const d=await api('/api/admin/payment');if(!d.status)return;
 $('#p_dana').value=d.dana||'';$('#p_name').value=d.dana_name||'';showQris(d.qris);$('#p_del').classList.toggle('hide',!d.qris)}
$('#p_qris').onchange=async e=>{const f=e.target.files[0];if(!f)return;
 try{newQris=await img2url(f,1000,.92);showQris(newQris);say($('#p_msg'),true,'Foto dipilih. Tap Simpan pembayaran.')}catch(x){newQris='';say($('#p_msg'),false,x.message)}};
$('#payf').onsubmit=async e=>{e.preventDefault();
 const b={dana:$('#p_dana').value.trim(),dana_name:$('#p_name').value.trim()};if(newQris)b.qris=newQris;
 const d=await post('/api/admin/payment',b);say($('#p_msg'),d.status,d.status?'Pengaturan pembayaran tersimpan.':errOf(d));
 if(d.status){newQris='';$('#p_qris').value='';loadPay()}};
$('#p_del').onclick=async()=>{if(!confirm('Hapus foto QRIS?'))return;
 const d=await post('/api/admin/payment',{dana:$('#p_dana').value.trim(),dana_name:$('#p_name').value.trim(),remove_qris:true});
 say($('#p_msg'),d.status,d.status?'Foto QRIS dihapus.':errOf(d));newQris='';$('#p_qris').value='';loadPay()};

$('#logout').onclick=async()=>{try{await fetch('/api/logout',{method:'POST'})}catch{}location.href='/login'};
init();loadWa();loadDeposits();loadUsers();loadPay();syncAll();loadRevenue();
/* ---------- Notifikasi push (seperti alzDev) ---------- */
const PUSH_OK=('serviceWorker' in navigator)&&('PushManager' in window)&&('Notification' in window);
let pushOn=false,lastEv=0;
function b64Key(b){const p='='.repeat((4-b.length%4)%4),r=atob((b+p).replace(/-/g,'+').replace(/_/g,'/')),a=new Uint8Array(r.length);for(let i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a}
function paintPush(){const st=$('#pn_st'),on=$('#pn_on'),ts=$('#pn_test');
 if(!PUSH_OK){st.textContent='Browser ini belum mendukung notifikasi.';on.classList.add('hide');return}
 if(Notification.permission==='denied'){st.textContent='Notifikasi diblokir. Izinkan lewat pengaturan situs di browser.';on.classList.add('hide');return}
 st.textContent=pushOn?'Aktif di perangkat ini ✓':'Belum aktif';
 on.textContent=pushOn?'Matikan notifikasi':'Aktifkan notifikasi';ts.classList.toggle('hide',!pushOn)}
async function pushSub(){const reg=await navigator.serviceWorker.register('/sw.js');await navigator.serviceWorker.ready;
 const k=await api('/api/admin/push/key');if(!k.key)throw new Error('Kunci notifikasi belum siap. Jalankan push.sql di Supabase.');
 const kb=b64Key(k.key);let sub=await reg.pushManager.getSubscription();
 if(sub){const cur=sub.options&&sub.options.applicationServerKey?new Uint8Array(sub.options.applicationServerKey):null;
  if(cur&&(cur.length!==kb.length||cur.some((v,i)=>v!==kb[i]))){await sub.unsubscribe();sub=null}}
 if(!sub)sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:kb});
 const d=await post('/api/admin/push/subscribe',sub.toJSON());if(!d.ok)throw new Error(d.error||'Gagal menyimpan langganan.')}
async function pushUnsub(){const reg=await navigator.serviceWorker.getRegistration('/sw.js');const sub=reg&&await reg.pushManager.getSubscription();
 if(sub){try{await post('/api/admin/push/unsubscribe',{endpoint:sub.endpoint})}catch{}await sub.unsubscribe()}}
$('#pn_on').onclick=async()=>{const m=$('#pn_msg');m.textContent='';
 try{if(pushOn){await pushUnsub();pushOn=false}
  else{const p=await Notification.requestPermission();if(p!=='granted'){say(m,false,'Izin notifikasi tidak diberikan.');paintPush();return}await pushSub();pushOn=true;say(m,true,'Notifikasi aktif. Coba tombol Tes notifikasi.')}}
 catch(e){say(m,false,e.message||'Gagal mengaktifkan notifikasi.')}paintPush()};
$('#pn_test').onclick=async()=>{const m=$('#pn_msg');try{const d=await post('/api/admin/push/test');say(m,d.ok,d.ok?'Tes dikirim. Notifikasi akan muncul sebentar lagi.':errOf(d))}catch{}};
async function initPush(){if(!PUSH_OK){paintPush();return}
 try{const reg=await navigator.serviceWorker.getRegistration('/sw.js');const sub=reg&&await reg.pushManager.getSubscription();
  pushOn=!!sub&&Notification.permission==='granted';
  if(pushOn)pushSub().catch(()=>{})}catch{}paintPush()}
// Saat dasbor terbuka: segarkan angka deposit/pesanan otomatis, dan tampilkan notifikasi lokal jika push belum aktif
async function pollEvents(){try{const d=await api('/api/admin/push/events?since='+lastEv);if(!d.ok)return;
 const ev=d.events||[];if(lastEv===0){lastEv=ev.length?ev[ev.length-1].id:-1;return}
 if(!ev.length)return;lastEv=ev[ev.length-1].id;
 if(ev.some(e=>e.type==='deposit'))loadDeposits();loadRevenue();
 if(!pushOn&&'Notification' in window&&Notification.permission==='granted')ev.forEach(e=>{try{new Notification(e.title,{body:e.body,tag:'alzz-'+e.id})}catch{}})}catch{}}
initPush();pollEvents();setInterval(pollEvents,25000);
if(['home','ord','dp','us','rec','pay','set'].includes(location.hash.slice(1)))tab(location.hash.slice(1));
`,
};
