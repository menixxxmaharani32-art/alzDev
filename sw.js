// Service worker ALZZ Store: menampilkan notifikasi di layar HP saat ada push dari server
var META = 'alzz-meta';

async function getMeta(key) {
  try {
    var c = await caches.open(META);
    var r = await c.match(key);
    return r ? await r.text() : null;
  } catch (e) { return null; }
}
async function setMeta(key, val) {
  try {
    var c = await caches.open(META);
    await c.put(key, new Response(String(val)));
  } catch (e) {}
}

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

async function handlePush() {
  var last = parseInt((await getMeta('last_id')) || '0', 10);
  var events = [];
  try {
    var res = await fetch('push_api.php?action=events&since=' + last, { credentials: 'same-origin', cache: 'no-store' });
    var data = await res.json();
    events = data.events || [];
  } catch (e) {}

  if (events.length === 0) {
    // Tidak ada yang baru (mungkin sudah ditampilkan): tampilkan ulang notifikasi terakhir dengan tag sama (diganti, bukan dobel)
    var saved = await getMeta('last_event');
    var ev = null;
    try { ev = saved ? JSON.parse(saved) : null; } catch (e) {}
    if (!ev) ev = { id: 0, title: 'ALZZ Store', body: 'Ada aktivitas baru di dashboard admin.', url: 'admin_dashboard.php' };
    return self.registration.showNotification(ev.title, {
      body: ev.body, tag: 'alzz-evt-' + ev.id, renotify: false,
      data: { url: ev.url }, icon: undefined
    });
  }

  var maxId = last;
  for (var i = 0; i < events.length; i++) {
    var e = events[i];
    if (e.id > maxId) maxId = e.id;
    await self.registration.showNotification(e.title, {
      body: e.body, tag: 'alzz-evt-' + e.id, renotify: true,
      vibrate: [200, 100, 200], data: { url: e.url }
    });
  }
  await setMeta('last_id', maxId);
  await setMeta('last_event', JSON.stringify(events[events.length - 1]));
}

self.addEventListener('push', function (event) {
  // Isi notifikasi ikut terkirim (terenkripsi) -> langsung tampil, tanpa menunggu apa pun
  var data = null;
  try { data = event.data ? event.data.json() : null; } catch (e) { data = null; }
  if (data && data.title) {
    var id = data.id || Date.now();
    event.waitUntil(
      self.registration.showNotification(data.title, {
        body: data.body || '', tag: 'alzz-evt-' + id, renotify: true,
        vibrate: [200, 100, 200], data: { url: data.url || 'admin_dashboard.php' }
      }).then(function () { return setMeta('last_id', id); })
    );
    return;
  }
  // Cara lama (push kosong): ambil isi dari server
  event.waitUntil(handlePush());
});

function b64uToUint8(b64u) {
  var pad = '='.repeat((4 - b64u.length % 4) % 4);
  var raw = atob((b64u + pad).replace(/-/g, '+').replace(/_/g, '/'));
  var arr = new Uint8Array(raw.length);
  for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
  return arr;
}

// Jika langganan HP berganti/kedaluwarsa, daftarkan ulang otomatis (tanpa perlu buka dashboard)
self.addEventListener('pushsubscriptionchange', function (event) {
  event.waitUntil((async function () {
    try {
      var k = await (await fetch('push_api.php?action=key', { credentials: 'same-origin', cache: 'no-store' })).json();
      if (!k.key) return;
      var sub = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToUint8(k.key) });
      await fetch('push_api.php?action=subscribe', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sub.toJSON())
      });
    } catch (e) {}
  })());
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || 'admin_dashboard.php';
  var target = new URL(url, self.registration.scope).href;
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if ('focus' in list[i]) { list[i].navigate(target); return list[i].focus(); }
      }
      return self.clients.openWindow(target);
    })
  );
});
