// ===== WEB PUSH (notifikasi ke layar HP admin, walau browser ditutup) =====
// Versi Node.js dari push_lib.php + push_api.php + sw.js milik alzDev.
// Tanpa library tambahan: hanya modul bawaan Node (crypto). VAPID + payload terenkripsi (RFC 8291).
// Kunci VAPID dibuat otomatis sekali lalu disimpan di tabel push_config (atau pakai env VAPID_PUBLIC & VAPID_PRIVATE).
const crypto = require('crypto');

const b64u = (buf) => Buffer.from(buf).toString('base64url');
const unb64u = (s) => Buffer.from(String(s), 'base64url');
const buf = (x) => Buffer.from(x); // hkdfSync mengembalikan ArrayBuffer

module.exports = function mountPush(app, d) {
  const { rest, adminOnly, run, enc } = d;
  let vapidCache = null;

  /* ---------- Kunci VAPID ---------- */
  async function getVapid() {
    if (vapidCache) return vapidCache;
    // 1) dari environment variable (jika diisi), format: VAPID_PRIVATE = JWK JSON atau "d" base64url
    if (process.env.VAPID_PUBLIC && process.env.VAPID_PRIVATE) {
      vapidCache = fromStored(process.env.VAPID_PUBLIC, process.env.VAPID_PRIVATE);
      if (vapidCache) return vapidCache;
    }
    // 2) dari database
    const rows = await rest('push_config', '?select=k,v&k=in.(vapid_public,vapid_private)');
    const cfg = Object.fromEntries((rows || []).map((r) => [r.k, r.v]));
    if (cfg.vapid_public && cfg.vapid_private) {
      vapidCache = fromStored(cfg.vapid_public, cfg.vapid_private);
      if (vapidCache) return vapidCache;
    }
    // 3) buat baru, simpan sekali
    const { publicKey, privateKey } = crypto.generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
    const jwk = privateKey.export({ format: 'jwk' });
    const pub = b64u(Buffer.concat([Buffer.from([4]), unb64u(jwk.x), unb64u(jwk.y)]));
    const priv = JSON.stringify({ kty: 'EC', crv: 'P-256', x: jwk.x, y: jwk.y, d: jwk.d });
    await rest('push_config', '', {
      method: 'POST', prefer: 'resolution=merge-duplicates',
      body: [{ k: 'vapid_public', v: pub }, { k: 'vapid_private', v: priv }],
    });
    vapidCache = { public: pub, key: crypto.createPrivateKey({ key: JSON.parse(priv), format: 'jwk' }) };
    return vapidCache;
  }
  function fromStored(pub, priv) {
    try {
      let jwk;
      if (String(priv).trim().startsWith('{')) jwk = JSON.parse(priv);
      else {
        const p = unb64u(pub); // 0x04 | x | y
        jwk = { kty: 'EC', crv: 'P-256', x: b64u(p.subarray(1, 33)), y: b64u(p.subarray(33, 65)), d: String(priv).trim() };
      }
      return { public: pub, key: crypto.createPrivateKey({ key: jwk, format: 'jwk' }) };
    } catch (e) { console.error('Kunci VAPID tidak valid:', e.message); return null; }
  }

  function vapidHeader(v, endpoint) {
    const u = new URL(endpoint);
    const head = b64u(JSON.stringify({ typ: 'JWT', alg: 'ES256' }));
    const claims = b64u(JSON.stringify({ aud: u.origin, exp: Math.floor(Date.now() / 1000) + 12 * 3600, sub: process.env.VAPID_SUBJECT || 'mailto:admin@alzzpedia.local' }));
    const data = head + '.' + claims;
    const sig = crypto.sign('sha256', Buffer.from(data), { key: v.key, dsaEncoding: 'ieee-p1363' }); // R||S 64 byte
    return 'vapid t=' + data + '.' + b64u(sig) + ', k=' + v.public;
  }

  /* ---------- Enkripsi payload (RFC 8291, aes128gcm) ---------- */
  function encrypt(payload, p256dh, auth) {
    const uaPub = unb64u(p256dh);
    const authSecret = unb64u(auth);
    if (uaPub.length !== 65 || authSecret.length < 8) return null;
    const ecdh = crypto.createECDH('prime256v1');
    const asPub = ecdh.generateKeys();
    const secret = ecdh.computeSecret(uaPub);
    const ikm = buf(crypto.hkdfSync('sha256', secret, authSecret, Buffer.concat([Buffer.from('WebPush: info\0'), uaPub, asPub]), 32));
    const salt = crypto.randomBytes(16);
    const cek = buf(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: aes128gcm\0'), 16));
    const nonce = buf(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: nonce\0'), 12));
    const c = crypto.createCipheriv('aes-128-gcm', cek, nonce);
    const ct = Buffer.concat([c.update(Buffer.concat([Buffer.from(payload), Buffer.from([2])])), c.final(), c.getAuthTag()]);
    const rs = Buffer.alloc(4); rs.writeUInt32BE(4096);
    return Buffer.concat([salt, rs, Buffer.from([asPub.length]), asPub, ct]);
  }

  /* ---------- Kirim 1 push ke 1 HP ---------- */
  async function sendOne(v, sub, payload) {
    const headers = { Authorization: vapidHeader(v, sub.endpoint), TTL: '86400', Urgency: 'high' };
    let body;
    if (payload && sub.p256dh && sub.auth) {
      try { body = encrypt(JSON.stringify(payload), sub.p256dh, sub.auth); } catch (e) { body = null; }
    }
    if (body) { headers['Content-Encoding'] = 'aes128gcm'; headers['Content-Type'] = 'application/octet-stream'; }
    // tanpa body = push kosong: sw.js lalu mengambil isi dari /api/admin/push/events
    const r = await fetch(sub.endpoint, { method: 'POST', headers, body: body || undefined, signal: AbortSignal.timeout(6000) });
    return r.status;
  }

  /* ---------- Catat kejadian + kirim ke semua HP admin ---------- */
  // Jangan pernah menggagalkan pesanan/pendaftaran karena notifikasi: semua error ditelan.
  // Pakai "await notify(...)" agar selesai sebelum fungsi serverless Vercel dihentikan.
  async function notify(type, title, body, url) {
    try {
      const t = String(title).slice(0, 190), b = String(body).slice(0, 300);
      const iso = new Date(Date.now() - 2 * 864e5).toISOString();
      rest('push_events', '?created_at=lt.' + enc(iso), { method: 'DELETE' }).catch(() => {});
      const [ev] = (await rest('push_events', '?select=id', { method: 'POST', prefer: 'return=representation', body: { type, title: t, body: b, url } })) || [];
      const subs = await rest('push_subs', '?select=id,endpoint,p256dh,auth');
      if (!subs || !subs.length) return;
      const v = await getVapid();
      const payload = { id: ev ? ev.id : Date.now(), type, title: t, body: b, url };
      await Promise.allSettled(subs.map(async (s) => {
        try {
          const code = await sendOne(v, s, payload);
          if (code === 404 || code === 410) await rest('push_subs', '?id=eq.' + s.id, { method: 'DELETE' });
          else if (code >= 400) console.error('Push ditolak', code);
        } catch (e) { console.error('Push gagal:', e.message); }
      }));
    } catch (e) { console.error('Notifikasi gagal:', e.message); }
  }

  /* ---------- API (hanya admin yang login) ---------- */
  app.get('/api/admin/push/key', adminOnly, run(async () => {
    const v = await getVapid();
    return { ok: true, key: v ? v.public : null };
  }));

  app.post('/api/admin/push/subscribe', adminOnly, run(async (req, res) => {
    const b = req.body || {};
    const endpoint = b.endpoint;
    if (typeof endpoint !== 'string' || !endpoint.startsWith('https://') || endpoint.length > 1000) { res.status(400); return { ok: false, error: 'endpoint tidak valid' }; }
    const k = b.keys && typeof b.keys === 'object' ? b.keys : {};
    const p256dh = typeof k.p256dh === 'string' && /^[A-Za-z0-9_-]{80,100}$/.test(k.p256dh) ? k.p256dh : '';
    const auth = typeof k.auth === 'string' && /^[A-Za-z0-9_-]{8,40}$/.test(k.auth) ? k.auth : '';
    await rest('push_subs', '', {
      method: 'POST', prefer: 'resolution=merge-duplicates',
      body: { endpoint_hash: crypto.createHash('sha256').update(endpoint).digest('hex'), endpoint, p256dh, auth, created_at: new Date().toISOString() },
    });
    return { ok: true };
  }));

  app.post('/api/admin/push/unsubscribe', adminOnly, run(async (req, res) => {
    const endpoint = (req.body || {}).endpoint;
    if (typeof endpoint !== 'string') { res.status(400); return { ok: false, error: 'endpoint tidak valid' }; }
    await rest('push_subs', '?endpoint_hash=eq.' + crypto.createHash('sha256').update(endpoint).digest('hex'), { method: 'DELETE' });
    return { ok: true };
  }));

  // Kirim notifikasi percobaan ke semua HP admin (tombol "Tes notifikasi")
  app.post('/api/admin/push/test', adminOnly, run(async () => {
    await notify('test', '✅ Notifikasi aktif', 'Notifikasi Alzz Pedia berhasil sampai ke HP ini.', '/admin');
    return { ok: true };
  }));

  app.get('/api/admin/push/events', adminOnly, run(async (req) => {
    const since = parseInt(req.query.since, 10) || 0;
    const q = since > 0 ? 'id=gt.' + since : 'created_at=gt.' + enc(new Date(Date.now() - 10 * 60000).toISOString());
    const rows = await rest('push_events', '?select=id,type,title,body,url&order=id.asc&limit=10&' + q);
    return { ok: true, events: rows || [] };
  }));

  /* ---------- Service worker (harus di root agar bisa mengontrol /admin) ---------- */
  app.get('/sw.js', (req, res) => {
    res.set({ 'Content-Type': 'application/javascript; charset=utf-8', 'Cache-Control': 'no-cache', 'Service-Worker-Allowed': '/' });
    res.send(SW);
  });

  return { notify };
};

/* ---------- Isi service worker ---------- */
const SW = `// Service worker Alzz Pedia: menampilkan notifikasi di layar HP saat ada push dari server
var META = 'alzz-meta';
async function getMeta(k){try{var c=await caches.open(META);var r=await c.match(k);return r?await r.text():null}catch(e){return null}}
async function setMeta(k,v){try{var c=await caches.open(META);await c.put(k,new Response(String(v)))}catch(e){}}

self.addEventListener('install',function(){self.skipWaiting()});
self.addEventListener('activate',function(e){e.waitUntil(self.clients.claim())});

async function handlePush(){
  var last=parseInt((await getMeta('last_id'))||'0',10);
  var events=[];
  try{
    var res=await fetch('/api/admin/push/events?since='+last,{credentials:'same-origin',cache:'no-store'});
    var data=await res.json();events=data.events||[];
  }catch(e){}
  if(events.length===0){
    var saved=await getMeta('last_event'),ev=null;
    try{ev=saved?JSON.parse(saved):null}catch(e){}
    if(!ev)ev={id:0,title:'Alzz Pedia',body:'Ada aktivitas baru di dasbor admin.',url:'/admin'};
    return self.registration.showNotification(ev.title,{body:ev.body,tag:'alzz-evt-'+ev.id,renotify:false,data:{url:ev.url}});
  }
  var maxId=last;
  for(var i=0;i<events.length;i++){
    var e=events[i];if(e.id>maxId)maxId=e.id;
    await self.registration.showNotification(e.title,{body:e.body,tag:'alzz-evt-'+e.id,renotify:true,vibrate:[200,100,200],data:{url:e.url}});
  }
  await setMeta('last_id',maxId);
  await setMeta('last_event',JSON.stringify(events[events.length-1]));
}

self.addEventListener('push',function(event){
  var data=null;
  try{data=event.data?event.data.json():null}catch(e){data=null}
  if(data&&data.title){
    var id=data.id||Date.now();
    event.waitUntil(self.registration.showNotification(data.title,{body:data.body||'',tag:'alzz-evt-'+id,renotify:true,vibrate:[200,100,200],data:{url:data.url||'/admin'}}).then(function(){return setMeta('last_id',id)}));
    return;
  }
  event.waitUntil(handlePush());
});

function b64uToUint8(s){
  var pad='='.repeat((4-s.length%4)%4),raw=atob((s+pad).replace(/-/g,'+').replace(/_/g,'/')),a=new Uint8Array(raw.length);
  for(var i=0;i<raw.length;i++)a[i]=raw.charCodeAt(i);return a;
}
// Jika langganan HP berganti/kedaluwarsa, daftarkan ulang otomatis
self.addEventListener('pushsubscriptionchange',function(event){
  event.waitUntil((async function(){
    try{
      var k=await (await fetch('/api/admin/push/key',{credentials:'same-origin',cache:'no-store'})).json();
      if(!k.key)return;
      var sub=await self.registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64uToUint8(k.key)});
      await fetch('/api/admin/push/subscribe',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(sub.toJSON())});
    }catch(e){}
  })());
});

self.addEventListener('notificationclick',function(event){
  event.notification.close();
  var url=(event.notification.data&&event.notification.data.url)||'/admin';
  var target=new URL(url,self.registration.scope).href;
  event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(function(list){
    for(var i=0;i<list.length;i++){if('focus' in list[i]){list[i].navigate(target);return list[i].focus()}}
    return self.clients.openWindow(target);
  }));
});
`;
